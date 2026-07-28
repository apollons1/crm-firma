<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Twilio\Security\RequestValidator;

class TwilioWhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($this->hasValidTwilioSignature($request), 403, 'Semnătură Twilio invalidă.');

        $messageSid = $request->string('MessageSid')->toString();

        if (blank($messageSid)) {
            return $this->emptyTwiml();
        }

        // Idempotență: dacă Twilio nu primește răspuns la timp, retrimite
        // webhook-ul cu același MessageSid — nu vrem să duplicăm mesajul.
        if (WhatsappMessage::where('twilio_message_sid', $messageSid)->exists()) {
            return $this->emptyTwiml();
        }

        $fromNumber = $this->stripWhatsAppPrefix($request->string('From')->toString());
        $toNumber = $this->stripWhatsAppPrefix($request->string('To')->toString());

        [$mediaUrl, $mediaType] = $this->firstMedia($request);

        $contact = $this->findContactByPhone($fromNumber);
        $opportunity = null;

        if ($contact) {
            $opportunity = Opportunity::where('contact_id', $contact->id)
                ->whereNotIn('status', ['won', 'lost'])
                ->orderByDesc('updated_at')
                ->first();
        }

        WhatsappMessage::create([
            'direction' => 'received',
            'from_number' => $fromNumber,
            'to_number' => $toNumber,
            'body' => $request->string('Body')->toString() ?: null,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'twilio_message_sid' => $messageSid,
            'status' => 'received',
            'client_id' => $contact?->client_id,
            'contact_id' => $contact?->id,
            'opportunity_id' => $opportunity?->id,
            'sent_at' => now(),
        ]);

        return $this->emptyTwiml();
    }

    private function hasValidTwilioSignature(Request $request): bool
    {
        $signature = $request->header('X-Twilio-Signature');

        if (blank($signature)) {
            return false;
        }

        $validator = new RequestValidator((string) config('services.twilio.token'));

        return $validator->validate($signature, $request->fullUrl(), $request->all());
    }

    private function stripWhatsAppPrefix(string $number): string
    {
        return str_replace('whatsapp:', '', $number);
    }

    /**
     * @return array{0: ?string, 1: ?string} [mediaUrl, mediaType]
     */
    private function firstMedia(Request $request): array
    {
        if ((int) $request->input('NumMedia', 0) < 1) {
            return [null, null];
        }

        return [
            $request->input('MediaUrl0'),
            $request->input('MediaContentType0'),
        ];
    }

    /**
     * Contactele pot avea telefonul salvat fie în format E.164 (+40...),
     * fie local (0...) — încercăm ambele variante pentru numere românești.
     */
    private function findContactByPhone(string $e164): ?Contact
    {
        $variants = [$e164];

        if (str_starts_with($e164, '+40')) {
            $variants[] = '0'.substr($e164, 3);
        }

        return Contact::whereIn('phone', $variants)->first();
    }

    private function emptyTwiml(): Response
    {
        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
            200,
            ['Content-Type' => 'text/xml']
        );
    }
}
