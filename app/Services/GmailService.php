<?php

namespace App\Services;

use App\Models\GoogleToken;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\ModifyMessageRequest;
use Illuminate\Support\Facades\Storage;
use League\OAuth2\Client\Provider\Google as GoogleProvider;
use RuntimeException;

class GmailService
{
    private Gmail $service;

    public function __construct(private readonly string $accessToken)
    {
        $client = new Client;
        $client->setAccessToken($this->accessToken);

        $this->service = new Gmail($client);
    }

    /**
     * Instanțiază serviciul pentru contul Gmail unic al firmei, reînnoind
     * automat access_token-ul via refresh_token dacă a expirat.
     */
    public static function forCompanyAccount(): self
    {
        $token = GoogleToken::first();

        if (! $token) {
            throw new RuntimeException('Niciun cont Gmail conectat. Vizitează /oauth/google/redirect.');
        }

        return self::forAccount($token);
    }

    /**
     * Instanțiază serviciul pentru un cont Gmail specific (folosit de
     * sincronizarea multi-cont), reînnoind access_token-ul dacă a expirat.
     */
    public static function forAccount(GoogleToken $token): self
    {
        if ($token->isExpired()) {
            self::refresh($token);
        }

        return new self($token->access_token);
    }

    private static function refresh(GoogleToken $token): void
    {
        $provider = new GoogleProvider([
            'clientId' => config('services.google.client_id'),
            'clientSecret' => config('services.google.client_secret'),
            'redirectUri' => config('services.google.redirect_uri'),
        ]);

        $newAccessToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $token->refresh_token,
        ]);

        $token->update([
            'access_token' => $newAccessToken->getToken(),
            'refresh_token' => $newAccessToken->getRefreshToken() ?? $token->refresh_token,
            'expires_at' => now()->setTimestamp($newAccessToken->getExpires()),
        ]);
    }

    /**
     * @param  array<int, string>  $attachments  Căi (pe disk-ul "public") ale fișierelor de atașat.
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $cc = null, array $attachments = []): Message
    {
        $headers = "To: {$to}\r\n";

        if (filled($cc)) {
            $headers .= "Cc: {$cc}\r\n";
        }

        $headers .= "Subject: {$subject}\r\n"
            ."MIME-Version: 1.0\r\n";

        $rawMessage = empty($attachments)
            ? $headers."Content-Type: text/html; charset=UTF-8\r\n\r\n".$body
            : $headers.$this->buildMultipartBody($body, $attachments);

        $message = new Message;
        $message->setRaw(rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '='));

        return $this->service->users_messages->send('me', $message);
    }

    /**
     * @param  array<int, string>  $attachments
     */
    private function buildMultipartBody(string $body, array $attachments): string
    {
        $boundary = 'boundary_'.bin2hex(random_bytes(16));

        $mime = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: text/html; charset=UTF-8\r\n\r\n"
            .$body."\r\n\r\n";

        foreach ($attachments as $path) {
            $filename = basename($path);
            $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
            $encoded = chunk_split(base64_encode(Storage::disk('public')->get($path)));

            $mime .= "--{$boundary}\r\n"
                ."Content-Type: {$mimeType}; name=\"{$filename}\"\r\n"
                ."Content-Disposition: attachment; filename=\"{$filename}\"\r\n"
                ."Content-Transfer-Encoding: base64\r\n\r\n"
                .$encoded."\r\n";
        }

        return $mime."--{$boundary}--";
    }

    /**
     * @return array<int, Message>
     */
    public function listEmails(string $query, int $maxResults = 50): array
    {
        $response = $this->service->users_messages->listUsersMessages('me', [
            'q' => $query,
            'maxResults' => $maxResults,
        ]);

        return $response->getMessages() ?? [];
    }

    public function getEmail(string $messageId): Message
    {
        return $this->service->users_messages->get('me', $messageId, ['format' => 'full']);
    }

    /**
     * Marchează mesajul ca citit în Gmail (scoate eticheta UNREAD).
     * Necesită scope-ul gmail.modify pe token — dacă lipsește, Google API
     * aruncă o excepție pe care apelantul trebuie să o trateze.
     */
    public function markAsRead(string $messageId): void
    {
        $request = new ModifyMessageRequest;
        $request->setRemoveLabelIds(['UNREAD']);

        $this->service->users_messages->modify('me', $messageId, $request);
    }

    /**
     * Descarcă conținutul brut (deja decodat) al unui atașament.
     */
    public function downloadAttachment(string $messageId, string $attachmentId): string
    {
        $attachment = $this->service->users_messages_attachments->get('me', $messageId, $attachmentId);

        $decoded = base64_decode(strtr($attachment->getData(), '-_', '+/'));

        return $decoded !== false ? $decoded : '';
    }
}
