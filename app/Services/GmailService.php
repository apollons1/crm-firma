<?php

namespace App\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

class GmailService
{
    private Gmail $service;

    public function __construct(private readonly string $accessToken)
    {
        $client = new Client;
        $client->setAccessToken($this->accessToken);

        $this->service = new Gmail($client);
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
