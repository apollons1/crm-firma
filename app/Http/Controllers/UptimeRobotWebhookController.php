<?php

namespace App\Http\Controllers;

use App\Models\SystemAlert;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Notifications\UptimeRobotAlertNotification;
use App\Services\WhatsAppService;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Webhook de la UptimeRobot (alert contact tip "Webhook", vezi configurarea
 * cerută în UptimeRobot: URL /webhooks/uptimerobot/{token}, POST Value
 * (JSON) cu placeholder-ele monitorID/monitorURL/monitorFriendlyName/
 * alertTypeFriendlyName/alertDetails/alertDuration). Neautentificat prin
 * sesiune — securizat printr-un token în URL, comparat cu hash_equals().
 */
class UptimeRobotWebhookController extends Controller
{
    public function __invoke(Request $request, string $secretToken): Response
    {
        $this->verifyToken($secretToken);

        $alertType = strtolower((string) $request->input('alertTypeFriendlyName', ''));

        match ($alertType) {
            'down' => $this->handleDown($request),
            'up' => $this->handleUp($request),
            default => Log::info('Webhook UptimeRobot: tip de alertă necunoscut, ignorat.', [
                'alertTypeFriendlyName' => $request->input('alertTypeFriendlyName'),
            ]),
        };

        return response('', 200);
    }

    private function verifyToken(string $secretToken): void
    {
        $expectedToken = (string) config('services.uptimerobot.webhook_token');

        abort_if(blank($expectedToken) || ! hash_equals($expectedToken, $secretToken), 403, 'Token invalid.');
    }

    private function handleDown(Request $request): void
    {
        $monitorFriendlyName = (string) $request->input('monitorFriendlyName', 'Site necunoscut');
        $monitorUrl = $request->input('monitorURL');
        $alertDetails = $request->input('alertDetails');
        $monitorId = $request->input('monitorID');

        $message = "{$monitorFriendlyName} nu răspunde.".(filled($alertDetails) ? " {$alertDetails}" : '');

        $alert = SystemAlert::create([
            'type' => 'downtime',
            'severity' => 'critical',
            'message' => $message,
            'metadata' => array_filter([
                'monitor_id' => $monitorId,
                'monitor_url' => $monitorUrl,
                'monitor_friendly_name' => $monitorFriendlyName,
                'alert_details' => $alertDetails,
            ]),
            'triggered_at' => now(),
        ]);

        Log::critical('UptimeRobot: site indisponibil.', [
            'monitor' => $monitorFriendlyName,
            'url' => $monitorUrl,
            'details' => $alertDetails,
        ]);

        $notification = new UptimeRobotAlertNotification(
            type: 'down',
            monitorFriendlyName: $monitorFriendlyName,
            monitorUrl: is_string($monitorUrl) ? $monitorUrl : null,
            alertDetails: is_string($alertDetails) ? $alertDetails : null,
        );

        $this->notifyAdmins($alert, $notification);
    }

    private function handleUp(Request $request): void
    {
        $monitorFriendlyName = (string) $request->input('monitorFriendlyName', 'Site necunoscut');
        $monitorId = $request->input('monitorID');
        $alertDurationSeconds = $request->input('alertDuration');

        $alert = $this->findOpenDowntimeAlert($monitorId);
        $downtimeMinutes = $this->resolveDowntimeMinutes($alert, $alertDurationSeconds);

        if ($alert) {
            $alert->update([
                'resolved_at' => now(),
                'metadata' => array_merge($alert->metadata ?? [], [
                    'downtime_minutes' => $downtimeMinutes,
                ]),
            ]);
        }

        Log::info('UptimeRobot: site revenit online.', [
            'monitor' => $monitorFriendlyName,
            'downtime_minutes' => $downtimeMinutes,
        ]);

        $notification = new UptimeRobotAlertNotification(
            type: 'up',
            monitorFriendlyName: $monitorFriendlyName,
            downtimeMinutes: $downtimeMinutes,
        );

        Notification::send(User::role('super_admin')->get(), $notification);
    }

    private function findOpenDowntimeAlert(mixed $monitorId): ?SystemAlert
    {
        $query = SystemAlert::query()->where('type', 'downtime')->whereNull('resolved_at');

        if (filled($monitorId)) {
            $byMonitor = (clone $query)
                ->where('metadata->monitor_id', (string) $monitorId)
                ->latest('triggered_at')
                ->first();

            if ($byMonitor) {
                return $byMonitor;
            }
        }

        return $query->latest('triggered_at')->first();
    }

    private function resolveDowntimeMinutes(?SystemAlert $alert, mixed $alertDurationSeconds): ?int
    {
        if (is_numeric($alertDurationSeconds)) {
            return (int) round(((float) $alertDurationSeconds) / 60);
        }

        if ($alert) {
            return (int) round($alert->triggered_at->diffInSeconds(now()) / 60);
        }

        return null;
    }

    private function notifyAdmins(SystemAlert $alert, UptimeRobotAlertNotification $notification): void
    {
        $admins = User::role('super_admin')->get();

        Notification::send($admins, $notification);

        $alert->update(['notified_users' => $admins->pluck('id')->all()]);

        $this->notifyByWhatsApp($admins, $notification);
    }

    /**
     * @param  Collection<int, User>  $admins
     */
    private function notifyByWhatsApp(Collection $admins, UptimeRobotAlertNotification $notification): void
    {
        foreach ($admins as $admin) {
            if (blank($admin->phone)) {
                continue;
            }

            $phone = PhoneNumber::toE164($admin->phone);

            // Regula 24h: fără template pre-aprobat pentru alerte de sistem,
            // deci în afara ferestrei omitem WhatsApp — emailul urgent rămâne
            // canalul sigur.
            if (! WhatsappMessage::isPhoneWithin24HourWindow($phone)) {
                Log::info('UptimeRobot: WhatsApp omis (în afara ferestrei de 24h, fără template aprobat).', [
                    'admin_id' => $admin->id,
                ]);

                continue;
            }

            try {
                $sid = app(WhatsAppService::class)->sendMessage($phone, $notification->getWhatsAppMessage());

                WhatsappMessage::create([
                    'direction' => 'sent',
                    'from_number' => str_replace('whatsapp:', '', (string) config('services.twilio.whatsapp_from')),
                    'to_number' => $phone,
                    'body' => $notification->getWhatsAppMessage(),
                    'twilio_message_sid' => $sid,
                    'status' => 'sent',
                    'sent_by_user_id' => null,
                    'sent_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::warning('UptimeRobot: trimiterea WhatsApp a eșuat.', [
                    'admin_id' => $admin->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
