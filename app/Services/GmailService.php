<?php

namespace App\Services;

use App\Models\GoogleToken;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
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

    public function sendEmail(string $to, string $subject, string $body): Message
    {
        $rawMessage = "To: {$to}\r\n"
            ."Subject: {$subject}\r\n"
            ."MIME-Version: 1.0\r\n"
            ."Content-Type: text/html; charset=UTF-8\r\n\r\n"
            .$body;

        $message = new Message;
        $message->setRaw(rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '='));

        return $this->service->users_messages->send('me', $message);
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
        return $this->service->users_messages->get('me', $messageId);
    }
}
