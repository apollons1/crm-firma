<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Convertește un număr local (ex: 0712345678) în format E.164 (+40712345678).
     * Dacă numărul are deja prefix internațional ("+"), îl lăsăm neschimbat.
     */
    public static function toE164(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', $phone);

        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+40'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}
