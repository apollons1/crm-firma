<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServerHealthAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'low_disk_space'|'failed_jobs_backlog'  $type
     */
    public function __construct(
        public readonly string $type,
        public readonly ?float $freeSpaceGb = null,
        public readonly ?int $failedJobsCount = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->type === 'low_disk_space'
            ? $this->lowDiskSpaceMail($notifiable)
            : $this->failedJobsBacklogMail($notifiable);
    }

    private function lowDiskSpaceMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Server: spațiu pe disc sub 5 GB')
            ->greeting("Bună, {$notifiable->name},")
            ->line("Serverul CRM AktivTherm are doar {$this->freeSpaceGb} GB liberi pe disc.")
            ->line('Recomandare: verifică storage/logs, backup-urile locale și fișierele temporare — eliberează spațiu înainte să afecteze aplicația.');
    }

    private function failedJobsBacklogMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Server: coadă de job-uri eșuate în creștere')
            ->greeting("Bună, {$notifiable->name},")
            ->line("Tabelul failed_jobs are {$this->failedJobsCount} intrări.")
            ->line('Recomandare: verifică storage/logs/laravel.log și rulează `php artisan queue:failed` pentru detalii.');
    }

    public function getWhatsAppMessage(): string
    {
        return $this->type === 'low_disk_space'
            ? "Server CRM: doar {$this->freeSpaceGb} GB liberi pe disc."
            : "Server CRM: {$this->failedJobsCount} job-uri eșuate în coadă (failed_jobs).";
    }
}
