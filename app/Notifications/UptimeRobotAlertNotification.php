<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UptimeRobotAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'down'|'up'  $type
     */
    public function __construct(
        public readonly string $type,
        public readonly string $monitorFriendlyName,
        public readonly ?string $monitorUrl = null,
        public readonly ?string $alertDetails = null,
        public readonly ?int $downtimeMinutes = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->type === 'down'
            ? $this->downMail($notifiable)
            : $this->upMail($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->filamentNotification()->getDatabaseMessage();
    }

    private function filamentNotification(): FilamentNotification
    {
        if ($this->type === 'down') {
            return FilamentNotification::make()
                ->title('Site indisponibil')
                ->body("{$this->monitorFriendlyName} nu răspunde.".(filled($this->alertDetails) ? " ({$this->alertDetails})" : ''))
                ->danger();
        }

        return FilamentNotification::make()
            ->title('Site revenit online')
            ->body($this->downtimeMinutes !== null
                ? "{$this->monitorFriendlyName} a revenit online după {$this->downtimeMinutes} minute de nefuncționare."
                : "{$this->monitorFriendlyName} a revenit online.")
            ->success();
    }

    private function downMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("URGENT: {$this->monitorFriendlyName} nu răspunde")
            ->greeting("Bună, {$notifiable->name},")
            ->line("Monitorul UptimeRobot a detectat că {$this->monitorFriendlyName} nu mai răspunde.");

        if (filled($this->monitorUrl)) {
            $mail->line("URL: {$this->monitorUrl}");
        }

        if (filled($this->alertDetails)) {
            $mail->line("Detalii: {$this->alertDetails}");
        }

        return $mail->line('Verifică imediat starea serverului și a aplicației.');
    }

    private function upMail(object $notifiable): MailMessage
    {
        $downtimeLine = $this->downtimeMinutes !== null
            ? "Site-ul a revenit online după aproximativ {$this->downtimeMinutes} minute de nefuncționare."
            : 'Site-ul a revenit online.';

        return (new MailMessage)
            ->subject("{$this->monitorFriendlyName} a revenit online")
            ->greeting("Bună, {$notifiable->name},")
            ->line($downtimeLine);
    }

    public function getWhatsAppMessage(): string
    {
        if ($this->type === 'down') {
            return "URGENT: {$this->monitorFriendlyName} nu răspunde.".(filled($this->alertDetails) ? " ({$this->alertDetails})" : '');
        }

        return $this->downtimeMinutes !== null
            ? "{$this->monitorFriendlyName} a revenit online după {$this->downtimeMinutes} minute."
            : "{$this->monitorFriendlyName} a revenit online.";
    }
}
