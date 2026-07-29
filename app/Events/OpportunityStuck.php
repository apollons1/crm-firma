<?php

namespace App\Events;

use App\Models\Opportunity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Declanșat de comanda scheduled opportunities:check-stuck (zilnic) pentru
 * fiecare oportunitate care nu a mai fost actualizată de prea mult timp
 * față de pragul propriului status.
 */
class OpportunityStuck
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Opportunity $opportunity,
        public readonly int $daysStuck,
    ) {}
}
