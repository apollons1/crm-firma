<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class TwilioWhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_TOKEN = 'test-twilio-auth-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.twilio.token' => self::AUTH_TOKEN]);
    }

    private function signedPost(array $params, ?string $signature = null): TestResponse
    {
        $url = route('webhooks.twilio.whatsapp');

        $signature ??= (new RequestValidator(self::AUTH_TOKEN))->computeSignature($url, $params);

        return $this->withHeaders(['X-Twilio-Signature' => $signature])
            ->post($url, $params);
    }

    public function test_request_without_valid_signature_is_rejected(): void
    {
        $response = $this->signedPost([
            'MessageSid' => 'SM123',
            'From' => 'whatsapp:+40712345678',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'Bună!',
            'NumMedia' => '0',
        ], signature: 'semnatura-falsa');

        $response->assertForbidden();
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_request_without_any_signature_header_is_rejected(): void
    {
        $response = $this->post(route('webhooks.twilio.whatsapp'), [
            'MessageSid' => 'SM123',
            'From' => 'whatsapp:+40712345678',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'Bună!',
        ]);

        $response->assertForbidden();
    }

    public function test_valid_message_is_saved_and_associated_with_contact_and_open_opportunity(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'phone' => '+40712345678',
        ]);
        $oldOpportunity = Opportunity::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'status' => 'lead',
            'updated_at' => now()->subDays(3),
        ]);
        $recentOpenOpportunity = Opportunity::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'status' => 'qualified',
            'updated_at' => now()->subHour(),
        ]);
        Opportunity::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'status' => 'won',
            'updated_at' => now(),
        ]);

        $response = $this->signedPost([
            'MessageSid' => 'SM123abc',
            'From' => 'whatsapp:+40712345678',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'Bună, aș vrea mai multe detalii.',
            'NumMedia' => '0',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $this->assertDatabaseCount('whatsapp_messages', 1);

        $message = WhatsappMessage::first();
        $this->assertSame('received', $message->direction);
        $this->assertSame('received', $message->status);
        $this->assertSame('+40712345678', $message->from_number);
        $this->assertSame('+14155238886', $message->to_number);
        $this->assertSame('Bună, aș vrea mai multe detalii.', $message->body);
        $this->assertSame('SM123abc', $message->twilio_message_sid);
        $this->assertSame($client->id, $message->client_id);
        $this->assertSame($contact->id, $message->contact_id);
        $this->assertSame($recentOpenOpportunity->id, $message->opportunity_id);
        $this->assertNull($message->sent_by_user_id);
    }

    public function test_message_from_unknown_number_is_saved_without_associations(): void
    {
        $response = $this->signedPost([
            'MessageSid' => 'SM999',
            'From' => 'whatsapp:+40799999999',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'Test',
            'NumMedia' => '0',
        ]);

        $response->assertOk();

        $message = WhatsappMessage::first();
        $this->assertNull($message->client_id);
        $this->assertNull($message->contact_id);
        $this->assertNull($message->opportunity_id);
    }

    public function test_local_format_phone_number_still_matches_contact(): void
    {
        Contact::factory()->create(['phone' => '0712345678']);

        $this->signedPost([
            'MessageSid' => 'SM777',
            'From' => 'whatsapp:+40712345678',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'Test',
            'NumMedia' => '0',
        ])->assertOk();

        $this->assertNotNull(WhatsappMessage::first()->contact_id);
    }

    public function test_duplicate_message_sid_is_not_saved_twice(): void
    {
        $params = [
            'MessageSid' => 'SM_DUPLICATE',
            'From' => 'whatsapp:+40712345678',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'Primul',
            'NumMedia' => '0',
        ];

        $this->signedPost($params)->assertOk();
        $this->signedPost($params)->assertOk();

        $this->assertDatabaseCount('whatsapp_messages', 1);
    }

    public function test_media_message_stores_url_and_type(): void
    {
        $response = $this->signedPost([
            'MessageSid' => 'SM_MEDIA',
            'From' => 'whatsapp:+40712345678',
            'To' => 'whatsapp:+14155238886',
            'NumMedia' => '1',
            'MediaUrl0' => 'https://api.twilio.com/media/fake.jpg',
            'MediaContentType0' => 'image/jpeg',
        ]);

        $response->assertOk();

        $message = WhatsappMessage::first();
        $this->assertSame('https://api.twilio.com/media/fake.jpg', $message->media_url);
        $this->assertSame('image/jpeg', $message->media_type);
        $this->assertNull($message->body);
    }
}
