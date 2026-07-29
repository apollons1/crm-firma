<?php

namespace App\Events;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Declanșat automat de Opportunity::booted() când status-ul trece la 'won'
 * (indiferent de calea prin care s-a întâmplat — acțiunea rapidă "Câștigată"
 * din tabel sau editare directă a formularului).
 */
class OpportunityWon
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Opportunity $opportunity,
        public readonly ?User $markedBy,
    ) {}
}
