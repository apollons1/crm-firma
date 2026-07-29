<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function signedPost(array $eventData, ?string $signatureHeader = null): TestResponse
    {
        $payload = json_encode($eventData);

        if ($signatureHeader === null) {
            $timestamp = time();
            $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);
            $signatureHeader = "t={$timestamp},v1={$signature}";
        }

        return $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function checkoutSessionCompletedEvent(string $sessionId, array $metadata, string $paymentStatus = 'paid'): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'payment_status' => $paymentStatus,
                    'payment_intent' => 'pi_fake123',
                    'metadata' => $metadata,
                ],
            ],
        ];
    }

    public function test_request_without_valid_signature_is_rejected(): void
    {
        $response = $this->signedPost(
            $this->checkoutSessionCompletedEvent('cs_123', ['opportunity_id' => '1']),
            signatureHeader: 't=1234567890,v1=semnatura-falsa',
        );

        $response->assertStatus(400);
    }

    public function test_request_without_signature_header_is_rejected(): void
    {
        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->checkoutSessionCompletedEvent('cs_123', ['opportunity_id' => '1'])),
        );

        $response->assertStatus(400);
    }

    public function test_event_without_opportunity_id_metadata_is_ignored_completely(): void
    {
        // Simulează un eveniment venit de la Selgora (celălalt platformă de
        // pe același cont Stripe) — nu are metadata.opportunity_id.
        $opportunity = Opportunity::factory()->create();
        $payment = Payment::factory()->create([
            'opportunity_id' => $opportunity->id,
            'stripe_session_id' => 'cs_selgora_999',
            'status' => 'pending',
        ]);

        $response = $this->signedPost(
            $this->checkoutSessionCompletedEvent('cs_selgora_999', ['selgora_order_id' => 'abc123'])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->stripe_payment_intent_id);
        $this->assertNull($payment->paid_at);
    }

    public function test_event_with_empty_metadata_is_ignored_completely(): void
    {
        $response = $this->signedPost(
            $this->checkoutSessionCompletedEvent('cs_no_metadata', [])
        );

        $response->assertOk();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_session_completed_updates_matching_payment(): void
    {
        $opportunity = Opportunity::factory()->create();
        $payment = Payment::factory()->create([
            'opportunity_id' => $opportunity->id,
            'stripe_session_id' => 'cs_crm_123',
            'status' => 'pending',
            'stripe_payment_intent_id' => null,
            'paid_at' => null,
        ]);

        $response = $this->signedPost(
            $this->checkoutSessionCompletedEvent('cs_crm_123', ['opportunity_id' => (string) $opportunity->id])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('pi_fake123', $payment->stripe_payment_intent_id);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_checkout_session_completed_with_no_matching_payment_does_not_error(): void
    {
        $opportunity = Opportunity::factory()->create();

        $response = $this->signedPost(
            $this->checkoutSessionCompletedEvent('cs_unknown_session', ['opportunity_id' => (string) $opportunity->id])
        );

        $response->assertOk();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unhandled_event_type_with_opportunity_metadata_does_not_error(): void
    {
        $opportunity = Opportunity::factory()->create();
        $payment = Payment::factory()->create([
            'opportunity_id' => $opportunity->id,
            'stripe_session_id' => 'cs_other_type',
            'status' => 'pending',
        ]);

        $event = [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_other_type',
                    'object' => 'checkout.session',
                    'metadata' => ['opportunity_id' => (string) $opportunity->id],
                ],
            ],
        ];

        $response = $this->signedPost($event);

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
    }
}
