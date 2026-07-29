<?php

namespace Tests\Feature;

use App\Events\OpportunityWon;
use App\Listeners\SendWonNotification;
use App\Models\AutomationSetting;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OpportunityWonAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_an_opportunity_won_dispatches_the_event(): void
    {
        Event::fake([OpportunityWon::class]);

        $opportunity = Opportunity::factory()->create(['status' => 'qualified']);

        $opportunity->update(['probability' => 80]);
        Event::assertNotDispatched(OpportunityWon::class);

        $opportunity->update(['status' => 'won']);
        Event::assertDispatched(OpportunityWon::class, fn ($event) => $event->opportunity->is($opportunity));
    }

    public function test_listener_skips_when_contact_has_no_phone(): void
    {
        $contact = Contact::factory()->create(['phone' => null]);
        $opportunity = Opportunity::factory()->create(['contact_id' => $contact->id, 'status' => 'won']);

        app(SendWonNotification::class)->handle(new OpportunityWon($opportunity, null));

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_listener_skips_when_automation_disabled(): void
    {
        AutomationSetting::set('opportunity_won.enabled', false);

        $contact = Contact::factory()->create(['phone' => '+40799001122']);
        $opportunity = Opportunity::factory()->create(['contact_id' => $contact->id, 'status' => 'won']);

        app(SendWonNotification::class)->handle(new OpportunityWon($opportunity, null));

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_sends_freeform_message_within_24h_window(): void
    {
        $this->mock(WhatsAppService::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with('+40799001122', 'Bună ziua Maria! Mulțumim pentru încrederea acordată. În următoarele 24h vă trimitem documentele finale. O zi excelentă!')
                ->andReturn('SM_FAKE_SID');
        });

        $contact = Contact::factory()->create(['first_name' => 'Maria', 'phone' => '+40799001122']);
        $opportunity = Opportunity::factory()->create(['contact_id' => $contact->id, 'client_id' => $contact->client_id, 'status' => 'won']);
        $user = User::factory()->create();

        WhatsappMessage::create([
            'direction' => 'received',
            'status' => 'received',
            'from_number' => '+40799001122',
            'to_number' => '+14155238886',
            'twilio_message_sid' => 'SM_INBOUND',
            'sent_at' => now()->subHour(),
        ]);

        app(SendWonNotification::class)->handle(new OpportunityWon($opportunity, $user));

        $message = WhatsappMessage::where('direction', 'sent')->first();
        $this->assertNotNull($message);
        $this->assertSame('sent', $message->status);
        $this->assertSame('SM_FAKE_SID', $message->twilio_message_sid);
        $this->assertSame($contact->id, $message->contact_id);
        $this->assertSame($user->id, $message->sent_by_user_id);
    }

    public function test_uses_fallback_template_outside_24h_window(): void
    {
        $template = WhatsappTemplate::factory()->create([
            'twilio_content_sid' => 'HXfake123',
            'body' => 'Bună {{1}}, mulțumim!',
            'status' => 'approved',
        ]);

        AutomationSetting::set('opportunity_won.fallback_template_id', $template->id);

        $this->mock(WhatsAppService::class, function ($mock) use ($template): void {
            $mock->shouldReceive('sendTemplate')
                ->once()
                ->with('+40799001122', $template->twilio_content_sid, ['1' => 'Maria'])
                ->andReturn('SM_TEMPLATE_SID');
        });

        $contact = Contact::factory()->create(['first_name' => 'Maria', 'phone' => '+40799001122']);
        $opportunity = Opportunity::factory()->create(['contact_id' => $contact->id, 'client_id' => $contact->client_id, 'status' => 'won']);

        // fără niciun mesaj primit — suntem în afara ferestrei de 24h
        app(SendWonNotification::class)->handle(new OpportunityWon($opportunity, null));

        $message = WhatsappMessage::first();
        $this->assertSame('sent', $message->status);
        $this->assertSame('SM_TEMPLATE_SID', $message->twilio_message_sid);
        $this->assertSame('Bună Maria, mulțumim!', $message->body);
    }

    public function test_fails_gracefully_outside_window_without_fallback_template(): void
    {
        $contact = Contact::factory()->create(['phone' => '+40799001122']);
        $opportunity = Opportunity::factory()->create(['contact_id' => $contact->id, 'client_id' => $contact->client_id, 'status' => 'won']);

        app(SendWonNotification::class)->handle(new OpportunityWon($opportunity, null));

        $message = WhatsappMessage::first();
        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('niciun template aprobat', $message->error_message);
    }
}
