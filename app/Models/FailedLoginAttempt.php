<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Evidență istorică a încercărilor eșuate de autentificare — folosită de
 * comanda de detectare a activității suspecte (brute-force / credential
 * stuffing). NU stochează parola introdusă, doar email-ul încercat.
 */
class FailedLoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'email_attempted',
        'user_agent',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }
}
