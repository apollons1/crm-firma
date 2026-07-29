<?php

namespace App\Providers;

use App\Events\OpportunityStuck;
use App\Events\OpportunityWon;
use App\Listeners\SendStuckOpportunityNotification;
use App\Listeners\SendWonNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OpportunityWon::class => [
            SendWonNotification::class,
        ],
        OpportunityStuck::class => [
            SendStuckOpportunityNotification::class,
        ],
    ];
}
