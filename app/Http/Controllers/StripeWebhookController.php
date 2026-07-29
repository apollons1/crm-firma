<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * IMPORTANT: contul Stripe e PARTAJAT cu Selgora, o altă platformă care
     * procesează plăți separat pe același cont. Acest webhook procesează
     * DOAR evenimentele care poartă metadata.opportunity_id (atașată de
     * StripeService la crearea sesiunii din CRM) — orice alt eveniment,
     * inclusiv tot ce vine de la Selgora, e ignorat complet, fără să scrie
     * nimic în baza de date.
     */
    public function __invoke(Request $request): Response
    {
        $event = $this->verifiedEvent($request);

        if (! $event) {
            return response('', 400);
        }

        $opportunityId = $event->data->object->metadata->opportunity_id ?? null;

        if (blank($opportunityId)) {
            return response('', 200);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            'checkout.session.expired' => $this->handleCheckoutSessionExpired($event),
            'payment_intent.payment_failed' => $this->handlePaymentIntentPaymentFailed($event),
            'charge.refunded' => $this->handleChargeRefunded($event),
            default => null,
        };

        return response('', 200);
    }

    private function verifiedEvent(Request $request): ?Event
    {
        $signature = $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');

        if (blank($signature) || blank($secret)) {
            return null;
        }

        try {
            return Webhook::constructEvent($request->getContent(), $signature, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Semnătură Stripe invalidă pe webhook.', ['exception' => $e->getMessage()]);

            return null;
        }
    }

    private function handleCheckoutSessionCompleted(Event $event): void
    {
        $session = $event->data->object;

        $payment = Payment::where('stripe_session_id', $session->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => $session->payment_status === 'paid' ? 'paid' : $payment->status,
            'stripe_payment_intent_id' => $session->payment_intent,
            'paid_at' => $session->payment_status === 'paid' ? now() : null,
        ]);
    }

    /**
     * Sesiunea de plată a expirat fără finalizare (clientul nu a plătit în
     * termenul acordat de Stripe — implicit 24h pentru Checkout Session).
     */
    private function handleCheckoutSessionExpired(Event $event): void
    {
        $session = $event->data->object;

        Payment::where('stripe_session_id', $session->id)->first()?->update([
            'status' => 'expired',
        ]);
    }

    /**
     * PaymentIntent-ul poate fi respins înainte ca sesiunea Checkout să se
     * finalizeze (card refuzat etc.) — Stripe NU populează session.payment_intent
     * sincron la crearea sesiunii (abia când clientul deschide efectiv pagina
     * de checkout), deci stripe_payment_intent_id e încă gol pe Payment în
     * acest moment. Căutăm mai întâi exact (în caz că a fost completat între
     * timp de alt eveniment), iar dacă nu găsim, cădem pe cea mai recentă
     * plată "pending" a aceleiași oportunități — și completăm
     * stripe_payment_intent_id acum, ca evenimentele următoare (ex:
     * charge.refunded) să se poată potrivi exact.
     */
    private function handlePaymentIntentPaymentFailed(Event $event): void
    {
        $paymentIntent = $event->data->object;
        $opportunityId = $paymentIntent->metadata->opportunity_id ?? null;

        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first()
            ?? Payment::where('opportunity_id', $opportunityId)
                ->where('status', 'pending')
                ->latest()
                ->first();

        $payment?->update([
            'status' => 'failed',
            'stripe_payment_intent_id' => $paymentIntent->id,
        ]);
    }

    /**
     * Charge-ul nu poartă stripe_session_id — regăsim plata prin
     * payment_intent-ul asociat charge-ului.
     */
    private function handleChargeRefunded(Event $event): void
    {
        $charge = $event->data->object;

        Payment::where('stripe_payment_intent_id', $charge->payment_intent)->first()?->update([
            'status' => 'refunded',
        ]);
    }
}
