<?php

namespace App\Services;

use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;

class GmailMessageParser
{
    public static function header(Message $message, string $name): ?string
    {
        $headers = $message->getPayload()?->getHeaders() ?? [];

        foreach ($headers as $header) {
            if (strcasecmp($header->getName(), $name) === 0) {
                return $header->getValue();
            }
        }

        return null;
    }

    /**
     * Extrage doar adresa de email din header-ul From ("Nume <email>" → "email").
     */
    public static function fromAddress(Message $message): ?string
    {
        $from = self::header($message, 'From');

        if ($from === null) {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return trim($matches[1]);
        }

        return trim($from);
    }

    /**
     * Extrage doar body-ul mesajului, preferând HTML; fallback la text simplu.
     */
    public static function body(Message $message): string
    {
        $payload = $message->getPayload();

        if ($payload === null) {
            return '';
        }

        $html = self::findPart($payload, 'text/html');

        if ($html !== null) {
            return $html;
        }

        $plain = self::findPart($payload, 'text/plain');

        if ($plain !== null) {
            return '<p>'.nl2br(e($plain)).'</p>';
        }

        return '';
    }

    /**
     * Caută recursiv (payload-ul Gmail imbrică multipart/alternative,
     * multipart/mixed etc.) prima parte cu mimeType-ul cerut.
     */
    private static function findPart(MessagePart $part, string $mimeType): ?string
    {
        if ($part->getMimeType() === $mimeType) {
            $data = $part->getBody()?->getData();

            return $data !== null ? self::decode($data) : null;
        }

        foreach ($part->getParts() ?? [] as $child) {
            $found = self::findPart($child, $mimeType);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function decode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'));

        return $decoded !== false ? $decoded : '';
    }
}
