<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuspiciousLoginActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'brute_force'|'credential_stuffing'  $type
     * @param  string  $identifier  IP-ul (brute_force) sau emailul (credential_stuffing)
     * @param  int  $count  numărul de încercări (brute_force) sau de IP-uri distincte (credential_stuffing)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $identifier,
        public readonly int $count,
        public readonly int $windowMinutes,
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
        return $this->type === 'brute_force'
            ? $this->bruteForceMail($notifiable)
            : $this->credentialStuffingMail($notifiable);
    }

    private function bruteForceMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Activitate suspectă: posibil atac brute-force asupra CRM')
            ->greeting("Bună, {$notifiable->name},")
            ->line("Adresa IP {$this->identifier} a generat {$this->count} încercări eșuate de autentificare în ultimele {$this->windowMinutes} minute.")
            ->line('Acest tipar sugerează un atac automatizat de tip brute-force asupra unuia sau mai multor conturi.')
            ->line('Recomandare: verifică jurnalul de încercări și, dacă e cazul, blochează adresa IP la nivel de firewall/server.');
    }

    private function credentialStuffingMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Activitate suspectă: posibil credential stuffing asupra CRM')
            ->greeting("Bună, {$notifiable->name},")
            ->line("Contul cu emailul {$this->identifier} a fost țintă a unor încercări de autentificare de pe {$this->count} adrese IP diferite, în ultimele {$this->windowMinutes} minute.")
            ->line('Acest tipar sugerează un atac de tip credential stuffing (parole furate din alte surse, testate aici).')
            ->line('Recomandare: contactează userul respectiv și ia în considerare resetarea forțată a parolei lui.');
    }

    public function getWhatsAppMessage(): string
    {
        return $this->type === 'brute_force'
            ? "Posibil atac brute-force: IP-ul {$this->identifier} a avut {$this->count} încercări eșuate de autentificare în ultimele {$this->windowMinutes} minute."
            : "Posibil credential stuffing: contul {$this->identifier} a fost încercat de pe {$this->count} adrese IP diferite în ultimele {$this->windowMinutes} minute.";
    }
}
