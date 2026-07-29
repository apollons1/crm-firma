<?php

namespace App\Services;

use App\Models\Opportunity;
use InvalidArgumentException;
use Stripe\Checkout\Session;
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
 */
class StripeService
{
    private StripeClient $client;

    public function __construct(private readonly string $secretKey)
    {
        $this->client = new StripeClient($this->secretKey);
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

        return $this->client->checkout->sessions->create([
            'mode' => 'payment',
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
            // sesiune de pe contul Stripe partajat cu Selgora.
            'metadata' => [
                'opportunity_id' => (string) $opportunity->id,
            ],
        ]);
    }
}
