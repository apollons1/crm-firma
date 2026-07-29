<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Opportunity;
use InvalidArgumentException;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\StripeClient;

/**
 * Wrapper peste clientul Stripe pentru generarea de linkuri de plată
 * (Checkout Session) pornind de la o oportunitate din CRM.
 *
 * IMPORTANT: contul Stripe e PARTAJAT cu Selgora, o altă platformă care
 * procesează plăți separat pe același cont. Fiecare sesiune creată de aici
 * ATAȘEAZĂ metadata.opportunity_id, astfel încât webhook-ul (StripeWebhookController)
 * să poată distinge evenimentele proprii de cele venite de la Selgora și să
 * ignore complet tot ce nu are acest marcaj.
 *
 * Nu folosim Laravel Cashier — sincronizarea Client ↔ Stripe Customer se
 * face manual, direct cu SDK-ul stripe/stripe-php.
 */
class StripeService
{
    private StripeClient $client;

    public function __construct(private readonly string $secretKey)
    {
        $this->client = new StripeClient($this->secretKey);
    }

    /**
     * Sincronizează un Client cu un Customer Stripe: dacă $client->stripe_id
     * există deja, preia customer-ul curent de la Stripe; altfel creează
     * unul nou și salvează id-ul pe Client.
     */
    public function syncCustomer(Client $client): Customer
    {
        if (filled($client->stripe_id)) {
            return $this->client->customers->retrieve($client->stripe_id);
        }

        $customer = $this->client->customers->create(array_filter([
            'name' => $client->name,
            'email' => $client->contacts()->first()?->email,
            'address' => filled($client->address) ? ['line1' => $client->address] : null,
        ]));

        $client->update(['stripe_id' => $customer->id]);

        return $customer;
    }

    /**
     * Creează un Checkout Session pentru plata unei oportunități.
     *
     * @param  float  $amount  Suma în unitatea majoră a monedei (ex: 1500.50 lei)
     * @param  string  $currency  Codul monedei ISO 4217 (implicit RON)
     */
    public function createCheckoutSessionForOpportunity(
        Opportunity $opportunity,
        float $amount,
        string $currency = 'RON',
    ): Session {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Suma de plată trebuie să fie mai mare decât 0.');
        }

        $customer = $this->syncCustomer($opportunity->client);

        return $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $customer->id,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => [
                        'name' => $opportunity->title,
                    ],
                    // Stripe cere suma în cea mai mică unitate a monedei
                    // (bani, nu lei) — de aici înmulțirea cu 100.
                    'unit_amount' => (int) round($amount * 100),
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('payments.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payments.cancel'),
            // Marcajul care distinge o sesiune creată de CRM de orice altă
            // sesiune de pe contul Stripe partajat cu Selgora. Metadata de pe
            // Checkout Session NU se propagă automat la PaymentIntent/Charge —
            // trebuie setată explicit și pe payment_intent_data, altfel
            // evenimentele payment_intent.* și charge.* nu ar purta niciodată
            // opportunity_id, iar webhook-ul le-ar ignora mereu.
            'metadata' => [
                'opportunity_id' => (string) $opportunity->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'opportunity_id' => (string) $opportunity->id,
                ],
            ],
        ]);
    }
}
