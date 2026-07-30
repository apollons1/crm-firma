<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly bool $changedBySelf,
        private readonly ?string $changedByName,
        private readonly string $ipAddress,
        private readonly Carbon $changedAt,
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
        $cine = $this->changedBySelf
            ? 'tine (din contul tău)'
            : 'administratorul '.($this->changedByName ?? 'necunoscut');

        return (new MailMessage)
            ->subject('Parola contului tău CRM a fost schimbată')
            ->greeting("Bună, {$notifiable->name},")
            ->line("Parola contului tău din CRM AktivTherm a fost schimbată de {$cine}.")
            ->line('Data și ora: '.$this->changedAt->format('d.m.Y H:i').'.')
            ->line("Adresa IP: {$this->ipAddress}.")
            ->line('Dacă NU ai fost tu cel care a făcut această schimbare, te rugăm să contactezi urgent un administrator.');
    }
}
