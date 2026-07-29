<?php

namespace Tests\Feature;

use App\Filament\Resources\Opportunities\Pages\EditOpportunity;
use App\Filament\Resources\Opportunities\RelationManagers\PaymentsRelationManager;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Stripe\Checkout\Session;
use Tests\TestCase;

class PaymentsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user);
    }

    /**
     * Regresie: "sendPaymentLink" e folosit atât ca acțiune pe rând (Opportunity
     * $record injectat de Filament din rândul tabelului), cât și ca acțiune de
     * antet aici (fără rând curent, doar owner record-ul relației) — cele două
     * contexte rezolvă oportunitatea diferit, iar ruperea uneia dintre căi nu
     * ar apărea altfel decât la deschiderea efectivă a tab-ului.
     */
    public function test_send_payment_link_header_action_is_available(): void
    {
        $opportunity = Opportunity::factory()->create();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $opportunity,
            'pageClass' => EditOpportunity::class,
        ])
            ->assertOk()
            ->assertActionExists(TestAction::make('sendPaymentLink')->table());
    }

    public function test_send_payment_link_header_action_creates_payment_for_owner_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create([
            'estimated_value' => 1000,
            'currency' => 'RON',
        ]);

        $this->mock(StripeService::class, function ($mock) use ($opportunity): void {
            $mock->shouldReceive('createCheckoutSessionForOpportunity')
                ->once()
                ->withArgs(fn ($opp, $amount, $currency) => $opp->is($opportunity) && $amount === 750.0 && $currency === 'RON')
                ->andReturn(Session::constructFrom([
                    'id' => 'cs_test_header_action',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_header_action',
                    'payment_intent' => 'pi_test_header_action',
                ]));
        });

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $opportunity,
            'pageClass' => EditOpportunity::class,
        ])
            ->callAction(TestAction::make('sendPaymentLink')->table(), [
                'amount' => 750,
                'currency' => 'RON',
            ])
            ->assertNotified();

        $payment = Payment::first();
        $this->assertNotNull($payment);
        $this->assertSame($opportunity->id, $payment->opportunity_id);
        $this->assertSame('cs_test_header_action', $payment->stripe_session_id);
        $this->assertSame('pi_test_header_action', $payment->stripe_payment_intent_id);
        $this->assertSame('pending', $payment->status);
    }
}
