<?php

namespace App\Providers;

use App\Events\OpportunityStuck;
use App\Events\OpportunityWon;
use App\Listeners\ClearLoginRateLimitOnSuccess;
use App\Listeners\RecordFailedLoginAttempt;
use App\Listeners\SendStuckOpportunityNotification;
use App\Listeners\SendWonNotification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
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
        Login::class => [
            ClearLoginRateLimitOnSuccess::class,
        ],
        Failed::class => [
            RecordFailedLoginAttempt::class,
        ],
    ];
}
