<?php

namespace App\Services;

use InvalidArgumentException;
use Twilio\Rest\Client;

/**
 * Wrapper peste clientul Twilio pentru trimiterea de mesaje WhatsApp
 * (text simplu, media și template-uri) din CRM.
 */
class WhatsAppService
{
    /**
     * Corpul mesajului text nu poate depăși această lungime — limită impusă
     * de WhatsApp/Twilio pentru mesajele de tip "session" sau "freeform".
     */
    private const MAX_BODY_LENGTH = 1600;

    private Client $client;

    /**
     * @param  string  $accountSid  SID-ul contului Twilio (config('services.twilio.sid'))
     * @param  string  $authToken  Token-ul de autentificare Twilio (config('services.twilio.token'))
     * @param  string  $fromNumber  Numărul WhatsApp al firmei, cu sau fără prefixul "whatsapp:"
     */
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $fromNumber,
    ) {
        $this->client = new Client($this->accountSid, $this->authToken);
    }

    /**
     * Trimite un mesaj text simplu prin WhatsApp.
     *
     * @param  string  $to  Numărul destinatarului, format E.164 (ex: +40712345678)
     * @return string messageSid-ul returnat de Twilio, util pentru tracking/status
     */
    public function sendMessage(string $to, string $body): string
    {
        $this->validatePhoneNumber($to);
        $this->validateBodyLength($body);

        $message = $this->client->messages->create(
            $this->toWhatsAppAddress($to),
            [
                'from' => $this->toWhatsAppAddress($this->fromNumber),
                'body' => $body,
            ]
        );

        return $message->sid;
    }

    /**
     * Trimite un mesaj cu media atașată (imagine, PDF etc.).
     *
     * @param  string  $to  Numărul destinatarului, format E.164
     * @param  string  $mediaUrl  URL public, accesibil de serverele Twilio (nu o cale locală de disc)
     * @return string messageSid-ul returnat de Twilio
     */
    public function sendMediaMessage(string $to, string $body, string $mediaUrl): string
    {
        $this->validatePhoneNumber($to);
        $this->validateBodyLength($body);

        $message = $this->client->messages->create(
            $this->toWhatsAppAddress($to),
            [
                'from' => $this->toWhatsAppAddress($this->fromNumber),
                'body' => $body,
                'mediaUrl' => [$mediaUrl],
            ]
        );

        return $message->sid;
    }

    /**
     * Trimite un mesaj pe bază de template WhatsApp (Twilio Content API) —
     * singura variantă acceptată în afara ferestrei de 24h de la ultimul
     * mesaj primit de la client.
     *
     * @param  string  $to  Numărul destinatarului, format E.164
     * @param  string  $contentSid  ID-ul template-ului aprobat în Twilio (ex: HXabc123...)
     * @param  array<int|string, string>  $variables  Valorile pentru {{1}}, {{2}}, ... din template
     * @return string messageSid-ul returnat de Twilio
     */
    public function sendTemplate(string $to, string $contentSid, array $variables = []): string
    {
        $this->validatePhoneNumber($to);

        $message = $this->client->messages->create(
            $this->toWhatsAppAddress($to),
            [
                'from' => $this->toWhatsAppAddress($this->fromNumber),
                'contentSid' => $contentSid,
                'contentVariables' => json_encode($variables),
            ]
        );

        return $message->sid;
    }

    /**
     * Validează formatul E.164 (ex: +40712345678) — obligatoriu pentru API-ul WhatsApp.
     */
    private function validatePhoneNumber(string $number): void
    {
        // "+" urmat de 1-15 cifre, prima cifră nenulă (standardul E.164)
        if (! preg_match('/^\+[1-9]\d{1,14}$/', $number)) {
            throw new InvalidArgumentException(
                "Numărul \"{$number}\" nu respectă formatul E.164 (ex: +40712345678)."
            );
        }
    }

    /**
     * WhatsApp/Twilio limitează corpul mesajului la MAX_BODY_LENGTH caractere.
     */
    private function validateBodyLength(string $body): void
    {
        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new InvalidArgumentException(
                'Mesajul depășește limita de '.self::MAX_BODY_LENGTH.' de caractere impusă de WhatsApp.'
            );
        }
    }

    /**
     * Twilio cere ca fiecare adresă (from/to) să fie prefixată cu "whatsapp:".
     * Acceptăm numărul cu sau fără prefix, ca să nu-l dublăm din greșeală.
     */
    private function toWhatsAppAddress(string $number): string
    {
        return str_starts_with($number, 'whatsapp:') ? $number : "whatsapp:{$number}";
    }
}
