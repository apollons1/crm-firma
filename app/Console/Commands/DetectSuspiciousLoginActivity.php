<?php

namespace App\Console\Commands;

use App\Models\FailedLoginAttempt;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Notifications\SuspiciousLoginActivityNotification;
use App\Services\WhatsAppService;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class DetectSuspiciousLoginActivity extends Command
{
    protected $signature = 'security:detect-suspicious-logins';

    protected $description = 'Detectează activitate suspectă de autentificare (brute-force / credential stuffing) și alertează adminii';

    private const WINDOW_MINUTES = 30;

    /**
     * Un singur IP cu acest număr de încercări eșuate în fereastră = probabil brute-force.
     */
    private const BRUTE_FORCE_THRESHOLD = 50;

    /**
     * Un singur email încercat de pe atâtea IP-uri distincte în fereastră = probabil credential stuffing.
     */
    private const CREDENTIAL_STUFFING_THRESHOLD = 20;

    public function handle(): int
    {
        $since = now()->subMinutes(self::WINDOW_MINUTES);

        $this->detectBruteForce($since);
        $this->detectCredentialStuffing($since);

        return self::SUCCESS;
    }

    private function detectBruteForce(Carbon $since): void
    {
        $suspiciousIps = FailedLoginAttempt::query()
            ->where('attempted_at', '>=', $since)
            ->select('ip_address', DB::raw('count(*) as attempts'))
            ->groupBy('ip_address')
            ->having('attempts', '>=', self::BRUTE_FORCE_THRESHOLD)
            ->get();

        foreach ($suspiciousIps as $row) {
            $this->sendAlert(
                type: 'brute_force',
                identifier: (string) $row->ip_address,
                count: (int) $row->attempts,
                dedupeKey: 'brute-force:'.$row->ip_address,
            );
        }
    }

    private function detectCredentialStuffing(Carbon $since): void
    {
        $suspiciousEmails = FailedLoginAttempt::query()
            ->where('attempted_at', '>=', $since)
            ->whereNotNull('email_attempted')
            ->select('email_attempted', DB::raw('count(distinct ip_address) as distinct_ips'))
            ->groupBy('email_attempted')
            ->having('distinct_ips', '>=', self::CREDENTIAL_STUFFING_THRESHOLD)
            ->get();

        foreach ($suspiciousEmails as $row) {
            $this->sendAlert(
                type: 'credential_stuffing',
                identifier: (string) $row->email_attempted,
                count: (int) $row->distinct_ips,
                dedupeKey: 'credential-stuffing:'.sha1((string) $row->email_attempted),
            );
        }
    }

    /**
     * @param  'brute_force'|'credential_stuffing'  $type
     */
    private function sendAlert(string $type, string $identifier, int $count, string $dedupeKey): void
    {
        // Comanda rulează la fiecare 10 minute, verificând o fereastră de 30
        // de minute — fără deduplicare, același atac în desfășurare ar
        // genera 2-3 alerte identice consecutive. Cache::add() e atomic:
        // întoarce false dacă o alertă a fost deja trimisă în fereastră.
        if (! Cache::add("security-alert-sent:{$dedupeKey}", true, now()->addMinutes(self::WINDOW_MINUTES))) {
            return;
        }

        Log::critical('Activitate suspectă de autentificare detectată.', [
            'type' => $type,
            'identifier' => $identifier,
            'count' => $count,
            'window_minutes' => self::WINDOW_MINUTES,
        ]);

        $this->warn("[{$type}] {$identifier}: {$count} — alertă trimisă adminilor.");

        $admins = User::role('super_admin')->get();

        $notification = new SuspiciousLoginActivityNotification($type, $identifier, $count, self::WINDOW_MINUTES);

        Notification::send($admins, $notification);

        $this->notifyByWhatsApp($admins, $notification);
    }

    /**
     * @param  Collection<int, User>  $admins
     */
    private function notifyByWhatsApp(Collection $admins, SuspiciousLoginActivityNotification $notification): void
    {
        foreach ($admins as $admin) {
            if (blank($admin->phone)) {
                continue;
            }

            $phone = PhoneNumber::toE164($admin->phone);

            // Regula 24h: fără template pre-aprobat pentru alerte de
            // securitate, deci în afara ferestrei pur și simplu omitem
            // WhatsApp pentru acest admin — emailul rămâne canalul sigur.
            if (! WhatsappMessage::isPhoneWithin24HourWindow($phone)) {
                Log::info('Alertă de securitate: WhatsApp omis (în afara ferestrei de 24h, fără template aprobat).', [
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
                Log::warning('Alertă de securitate: trimiterea WhatsApp a eșuat.', [
                    'admin_id' => $admin->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
