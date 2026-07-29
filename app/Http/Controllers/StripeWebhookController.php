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

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutSessionCompleted($event);
        }

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
}
