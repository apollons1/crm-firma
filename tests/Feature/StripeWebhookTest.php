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

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function checkoutSessionExpiredEvent(string $sessionId, array $metadata): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'metadata' => $metadata,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function paymentIntentPaymentFailedEvent(string $paymentIntentId, array $metadata): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'object' => 'payment_intent',
                    'metadata' => $metadata,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function chargeRefundedEvent(string $paymentIntentId, array $metadata): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_'.uniqid(),
                    'object' => 'charge',
                    'payment_intent' => $paymentIntentId,
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
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_other_type',
                    'object' => 'payment_intent',
                    'metadata' => ['opportunity_id' => (string) $opportunity->id],
                ],
            ],
        ];

        $response = $this->signedPost($event);

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
    }

    public function test_checkout_session_expired_marks_payment_expired(): void
    {
        $opportunity = Opportunity::factory()->create();
        $payment = Payment::factory()->create([
            'opportunity_id' => $opportunity->id,
            'stripe_session_id' => 'cs_expired_123',
            'status' => 'pending',
        ]);

        $response = $this->signedPost(
            $this->checkoutSessionExpiredEvent('cs_expired_123', ['opportunity_id' => (string) $opportunity->id])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('expired', $payment->status);
    }

    public function test_checkout_session_expired_without_opportunity_metadata_is_ignored(): void
    {
        $payment = Payment::factory()->create([
            'stripe_session_id' => 'cs_expired_selgora',
            'status' => 'pending',
        ]);

        $response = $this->signedPost(
            $this->checkoutSessionExpiredEvent('cs_expired_selgora', [])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
    }

    public function test_checkout_session_expired_with_no_matching_payment_does_not_error(): void
    {
        $response = $this->signedPost(
            $this->checkoutSessionExpiredEvent('cs_unknown', ['opportunity_id' => '1'])
        );

        $response->assertOk();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_intent_payment_failed_marks_payment_failed_via_exact_match(): void
    {
        $opportunity = Opportunity::factory()->create();
        $payment = Payment::factory()->create([
            'opportunity_id' => $opportunity->id,
            'stripe_payment_intent_id' => 'pi_failed_123',
            'status' => 'pending',
        ]);

        $response = $this->signedPost(
            $this->paymentIntentPaymentFailedEvent('pi_failed_123', ['opportunity_id' => (string) $opportunity->id])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
    }

    /**
     * Regresie: Stripe NU populează session.payment_intent sincron la
     * crearea sesiunii (abia când clientul deschide pagina de checkout), deci
     * la momentul unui card refuzat, payments.stripe_payment_intent_id e
     * încă gol/null în DB — potrivirea exactă eșuează mereu în acest caz.
     * Webhook-ul trebuie să cadă pe cea mai recentă plată "pending" a
     * aceleiași oportunități și să completeze stripe_payment_intent_id.
     */
    public function test_payment_intent_payment_failed_falls_back_to_pending_payment_and_backfills_intent_id(): void
    {
        $opportunity = Opportunity::factory()->create();
        $payment = Payment::factory()->create([
            'opportunity_id' => $opportunity->id,
            'stripe_payment_intent_id' => null,
            'status' => 'pending',
        ]);

        $response = $this->signedPost(
            $this->paymentIntentPaymentFailedEvent('pi_3TyYzyLpfin6pvfl2y4nX8XE', ['opportunity_id' => (string) $opportunity->id])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
        $this->assertSame('pi_3TyYzyLpfin6pvfl2y4nX8XE', $payment->stripe_payment_intent_id);
    }

    public function test_payment_intent_payment_failed_with_no_pending_payment_for_opportunity_does_not_error(): void
    {
        $opportunity = Opportunity::factory()->create();
        $payment = Payment::factory()->create([
            'opportunity_id' => $opportunity->id,
            'stripe_payment_intent_id' => null,
            'status' => 'paid',
        ]);

        $response = $this->signedPost(
            $this->paymentIntentPaymentFailedEvent('pi_unrelated', ['opportunity_id' => (string) $opportunity->id])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNull($payment->stripe_payment_intent_id);
    }

    public function test_payment_intent_payment_failed_without_opportunity_metadata_is_ignored(): void
    {
        // Simulează un PaymentIntent eșuat de pe contul Selgora — fără
        // opportunity_id, nu trebuie atins niciun rând din payments.
        $payment = Payment::factory()->create([
            'stripe_payment_intent_id' => 'pi_selgora_failed',
            'status' => 'pending',
        ]);

        $response = $this->signedPost(
            $this->paymentIntentPaymentFailedEvent('pi_selgora_failed', [])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
    }

    public function test_charge_refunded_marks_payment_refunded(): void
    {
        $opportunity = Opportunity::factory()->create();
        $payment = Payment::factory()->create([
            'opportunity_id' => $opportunity->id,
            'stripe_payment_intent_id' => 'pi_refunded_123',
            'status' => 'paid',
        ]);

        $response = $this->signedPost(
            $this->chargeRefundedEvent('pi_refunded_123', ['opportunity_id' => (string) $opportunity->id])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
    }

    public function test_charge_refunded_without_opportunity_metadata_is_ignored(): void
    {
        $payment = Payment::factory()->create([
            'stripe_payment_intent_id' => 'pi_selgora_refund',
            'status' => 'paid',
        ]);

        $response = $this->signedPost(
            $this->chargeRefundedEvent('pi_selgora_refund', [])
        );

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }

    public function test_charge_refunded_with_no_matching_payment_does_not_error(): void
    {
        $response = $this->signedPost(
            $this->chargeRefundedEvent('pi_unknown', ['opportunity_id' => '1'])
        );

        $response->assertOk();
        $this->assertDatabaseCount('payments', 0);
    }
}
