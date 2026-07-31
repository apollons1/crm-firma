<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WhatsappMessage;
use App\Notifications\ServerHealthAlertNotification;
use App\Services\WhatsAppService;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MonitorServerHealth extends Command
{
    protected $signature = 'monitoring:check-server-health';

    protected $description = 'Verifică spațiul liber pe disc și mărimea cozii failed_jobs, alertează super_admin dacă pragurile sunt depășite';

    private const MIN_FREE_DISK_GB = 5.0;

    private const MAX_FAILED_JOBS = 50;

    private const ALERT_DEDUPE_MINUTES = 30;

    public function handle(): int
    {
        $this->checkDiskSpace();
        $this->checkFailedJobsBacklog();

        return self::SUCCESS;
    }

    /**
     * Izolat într-o metodă proprie ca să poată fi suprascrisă în teste
     * (disk_free_space() reflectă discul real al mașinii care rulează
     * testele, nu poate fi controlat altfel).
     */
    protected function freeDiskSpaceBytes(): int|false
    {
        return disk_free_space(storage_path());
    }

    private function checkDiskSpace(): void
    {
        $freeBytes = $this->freeDiskSpaceBytes();

        if ($freeBytes === false) {
            Log::warning('Monitorizare server: nu s-a putut citi spațiul liber pe disc.');

            return;
        }

        $freeGb = $freeBytes / 1024 / 1024 / 1024;

        if ($freeGb >= self::MIN_FREE_DISK_GB) {
            return;
        }

        $this->sendAlert(
            notification: new ServerHealthAlertNotification(type: 'low_disk_space', freeSpaceGb: round($freeGb, 2)),
            dedupeKey: 'low-disk-space',
        );
    }

    private function checkFailedJobsBacklog(): void
    {
        $count = DB::table('failed_jobs')->count();

        if ($count <= self::MAX_FAILED_JOBS) {
            return;
        }

        $this->sendAlert(
            notification: new ServerHealthAlertNotification(type: 'failed_jobs_backlog', failedJobsCount: $count),
            dedupeKey: 'failed-jobs-backlog',
        );
    }

    private function sendAlert(ServerHealthAlertNotification $notification, string $dedupeKey): void
    {
        // Comanda rulează la fiecare 5 minute — fără deduplicare, o condiție
        // persistentă (disc plin, coadă blocată) ar genera o alertă nouă la
        // fiecare rulare. Cache::add() e atomic: întoarce false dacă o
        // alertă a fost deja trimisă în fereastră.
        if (! Cache::add("server-health-alert-sent:{$dedupeKey}", true, now()->addMinutes(self::ALERT_DEDUPE_MINUTES))) {
            return;
        }

        Log::critical('Alertă sănătate server.', [
            'type' => $notification->type,
            'free_space_gb' => $notification->freeSpaceGb,
            'failed_jobs_count' => $notification->failedJobsCount,
        ]);

        $this->warn("[{$notification->type}] alertă trimisă adminilor.");

        $admins = User::role('super_admin')->get();

        Notification::send($admins, $notification);

        $this->notifyByWhatsApp($admins, $notification);
    }

    /**
     * @param  Collection<int, User>  $admins
     */
    private function notifyByWhatsApp(Collection $admins, ServerHealthAlertNotification $notification): void
    {
        foreach ($admins as $admin) {
            if (blank($admin->phone)) {
                continue;
            }

            $phone = PhoneNumber::toE164($admin->phone);

            // Regula 24h: fără template pre-aprobat pentru alerte de server,
            // deci în afara ferestrei pur și simplu omitem WhatsApp pentru
            // acest admin — emailul rămâne canalul sigur.
            if (! WhatsappMessage::isPhoneWithin24HourWindow($phone)) {
                Log::info('Alertă server: WhatsApp omis (în afara ferestrei de 24h, fără template aprobat).', [
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
                Log::warning('Alertă server: trimiterea WhatsApp a eșuat.', [
                    'admin_id' => $admin->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
