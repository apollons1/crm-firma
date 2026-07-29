<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_amount_is_rejected(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        (new StripeService('sk_test_fake'))->createCheckoutSessionForOpportunity($opportunity, 0);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        (new StripeService('sk_test_fake'))->createCheckoutSessionForOpportunity($opportunity, -50);
    }
}
