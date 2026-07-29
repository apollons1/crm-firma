<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $fakeHttp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeHttp = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->fakeHttp);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

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

    public function test_sync_customer_creates_new_customer_when_client_has_no_stripe_id(): void
    {
        $client = Client::factory()->create(['stripe_id' => null, 'name' => 'Acme SRL', 'address' => 'Str. Exemplu 1']);
        Contact::factory()->create(['client_id' => $client->id, 'email' => 'contact@acme.ro']);

        $this->fakeHttp->queueResponse(['id' => 'cus_new123', 'object' => 'customer']);

        $customer = (new StripeService('sk_test_fake'))->syncCustomer($client);

        $this->assertSame('cus_new123', $customer->id);
        $this->assertSame('cus_new123', $client->fresh()->stripe_id);

        $this->assertCount(1, $this->fakeHttp->requests);
        $this->assertSame('post', $this->fakeHttp->requests[0]['method']);
        $this->assertStringContainsString('/v1/customers', $this->fakeHttp->requests[0]['url']);
        $this->assertSame('Acme SRL', $this->fakeHttp->requests[0]['params']['name']);
        $this->assertSame('contact@acme.ro', $this->fakeHttp->requests[0]['params']['email']);
        $this->assertSame('Str. Exemplu 1', $this->fakeHttp->requests[0]['params']['address']['line1']);
    }

    public function test_sync_customer_retrieves_existing_customer_without_creating_a_new_one(): void
    {
        $client = Client::factory()->create(['stripe_id' => 'cus_existing123']);

        $this->fakeHttp->queueResponse(['id' => 'cus_existing123', 'object' => 'customer']);

        $customer = (new StripeService('sk_test_fake'))->syncCustomer($client);

        $this->assertSame('cus_existing123', $customer->id);
        $this->assertSame('cus_existing123', $client->fresh()->stripe_id);

        $this->assertCount(1, $this->fakeHttp->requests);
        $this->assertSame('get', $this->fakeHttp->requests[0]['method']);
        $this->assertStringContainsString('/v1/customers/cus_existing123', $this->fakeHttp->requests[0]['url']);
    }

    public function test_checkout_session_is_created_with_synced_customer_id(): void
    {
        $client = Client::factory()->create(['stripe_id' => null]);
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);

        $this->fakeHttp->queueResponse(['id' => 'cus_abc123', 'object' => 'customer']);
        $this->fakeHttp->queueResponse([
            'id' => 'cs_test_abc123',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc123',
        ]);

        $session = (new StripeService('sk_test_fake'))->createCheckoutSessionForOpportunity($opportunity, 500);

        $this->assertSame('cs_test_abc123', $session->id);
        $this->assertSame('cus_abc123', $client->fresh()->stripe_id);

        $this->assertCount(2, $this->fakeHttp->requests);
        $this->assertStringContainsString('/v1/checkout/sessions', $this->fakeHttp->requests[1]['url']);
        $this->assertSame('cus_abc123', $this->fakeHttp->requests[1]['params']['customer']);
        $this->assertSame((string) $opportunity->id, $this->fakeHttp->requests[1]['params']['metadata']['opportunity_id']);
    }

    public function test_checkout_session_reuses_existing_customer_without_recreating(): void
    {
        $client = Client::factory()->create(['stripe_id' => 'cus_already_synced']);
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);

        $this->fakeHttp->queueResponse(['id' => 'cus_already_synced', 'object' => 'customer']);
        $this->fakeHttp->queueResponse([
            'id' => 'cs_test_def456',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_def456',
        ]);

        (new StripeService('sk_test_fake'))->createCheckoutSessionForOpportunity($opportunity, 500);

        $this->assertSame('get', $this->fakeHttp->requests[0]['method']);
        $this->assertStringContainsString('/v1/customers/cus_already_synced', $this->fakeHttp->requests[0]['url']);
        $this->assertSame('cus_already_synced', $this->fakeHttp->requests[1]['params']['customer']);
    }
}
