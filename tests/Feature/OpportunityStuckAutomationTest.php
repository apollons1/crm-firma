<?php

namespace Tests\Feature;

use App\Events\OpportunityStuck;
use App\Listeners\SendStuckOpportunityNotification;
use App\Models\AutomationSetting;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OpportunityStuckAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_event_only_for_opportunities_past_their_status_threshold(): void
    {
        Event::fake([OpportunityStuck::class]);

        $leadOk = Opportunity::factory()->create(['status' => 'lead', 'updated_at' => now()->subDays(10)]);
        $leadStuck = Opportunity::factory()->create(['status' => 'lead', 'updated_at' => now()->subDays(15)]);
        $proposalStuck = Opportunity::factory()->create(['status' => 'proposal', 'updated_at' => now()->subDays(25)]);
        $negotiationOk = Opportunity::factory()->create(['status' => 'negotiation', 'updated_at' => now()->subDays(25)]);
        $wonOld = Opportunity::factory()->create(['status' => 'won', 'updated_at' => now()->subDays(100)]);

        Artisan::call('opportunities:check-stuck', ['--force' => true]);

        Event::assertDispatched(OpportunityStuck::class, fn ($e) => $e->opportunity->is($leadStuck) && $e->daysStuck === 15);
        Event::assertDispatched(OpportunityStuck::class, fn ($e) => $e->opportunity->is($proposalStuck));
        Event::assertNotDispatched(OpportunityStuck::class, fn ($e) => $e->opportunity->is($leadOk));
        Event::assertNotDispatched(OpportunityStuck::class, fn ($e) => $e->opportunity->is($negotiationOk));
        Event::assertNotDispatched(OpportunityStuck::class, fn ($e) => $e->opportunity->is($wonOld));
    }

    public function test_command_respects_custom_thresholds_from_settings(): void
    {
        Event::fake([OpportunityStuck::class]);

        AutomationSetting::set('opportunity_stuck.days_lead', 5);

        $leadStuck = Opportunity::factory()->create(['status' => 'lead', 'updated_at' => now()->subDays(6)]);

        Artisan::call('opportunities:check-stuck', ['--force' => true]);

        Event::assertDispatched(OpportunityStuck::class, fn ($e) => $e->opportunity->is($leadStuck));
    }

    public function test_command_only_runs_at_the_configured_hour_unless_forced(): void
    {
        Event::fake([OpportunityStuck::class]);

        $wrongHour = now()->hour === 3 ? 4 : 3;
        AutomationSetting::set('opportunity_stuck.send_hour', $wrongHour);

        Opportunity::factory()->create(['status' => 'lead', 'updated_at' => now()->subDays(20)]);

        Artisan::call('opportunities:check-stuck');
        Event::assertNotDispatched(OpportunityStuck::class);

        Artisan::call('opportunities:check-stuck', ['--force' => true]);
        Event::assertDispatched(OpportunityStuck::class);
    }

    public function test_command_skips_entirely_when_automation_disabled(): void
    {
        Event::fake([OpportunityStuck::class]);

        AutomationSetting::set('opportunity_stuck.enabled', false);

        Opportunity::factory()->create(['status' => 'lead', 'updated_at' => now()->subDays(20)]);

        Artisan::call('opportunities:check-stuck', ['--force' => true]);

        Event::assertNotDispatched(OpportunityStuck::class);
    }

    public function test_listener_notifies_the_responsible_user_by_phone(): void
    {
        $this->mock(WhatsAppService::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn('SM_FAKE');
        });

        $user = User::factory()->create(['name' => 'Andrei Popescu', 'phone' => '+40711223344']);
        $opportunity = Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'lead', 'title' => 'Automatizare procese']);

        WhatsappMessage::create([
            'direction' => 'received',
            'status' => 'received',
            'from_number' => '+40711223344',
            'to_number' => '+14155238886',
            'twilio_message_sid' => 'SM_INBOUND_USER',
            'sent_at' => now()->subMinutes(30),
        ]);

        app(SendStuckOpportunityNotification::class)->handle(new OpportunityStuck($opportunity, 18));

        $message = WhatsappMessage::where('direction', 'sent')->first();
        $this->assertNotNull($message);
        $this->assertSame('+40711223344', $message->to_number);
        $this->assertStringContainsString('Andrei Popescu', $message->body);
        $this->assertStringContainsString('Automatizare procese', $message->body);
        $this->assertStringContainsString('18', $message->body);
    }

    public function test_listener_skips_when_responsible_user_has_no_phone(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $opportunity = Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'lead']);

        app(SendStuckOpportunityNotification::class)->handle(new OpportunityStuck($opportunity, 20));

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_listener_skips_when_opportunity_has_no_responsible_user(): void
    {
        $opportunity = Opportunity::factory()->create(['user_id' => null, 'status' => 'lead']);

        app(SendStuckOpportunityNotification::class)->handle(new OpportunityStuck($opportunity, 20));

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }
}
