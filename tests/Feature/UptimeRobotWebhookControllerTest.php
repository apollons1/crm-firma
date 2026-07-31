<?php

namespace Tests\Feature;

use App\Models\SystemAlert;
use App\Models\User;
use App\Notifications\UptimeRobotAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Testează App\Http\Controllers\UptimeRobotWebhookController — validarea
 * token-ului din URL, crearea/rezolvarea intrărilor din system_alerts, și
 * notificarea super_admin la Down/Up. Admin-ul de test nu are telefon
 * (phone null), deci trimiterea WhatsApp e omisă automat — fără apel real
 * către Twilio.
 */
class UptimeRobotWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-secret';

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.uptimerobot.webhook_token' => self::TOKEN]);

        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'sales_rep', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        Notification::fake();
    }

    private function downPayload(array $overrides = []): array
    {
        return array_merge([
            'monitorID' => '12345',
            'monitorURL' => 'https://crm.aktivtherm.com',
            'monitorFriendlyName' => 'CRM AktivTherm',
            'alertTypeFriendlyName' => 'Down',
            'alertDetails' => 'connection timeout',
        ], $overrides);
    }

    private function upPayload(array $overrides = []): array
    {
        return array_merge([
            'monitorID' => '12345',
            'monitorURL' => 'https://crm.aktivtherm.com',
            'monitorFriendlyName' => 'CRM AktivTherm',
            'alertTypeFriendlyName' => 'Up',
        ], $overrides);
    }

    public function test_rejects_requests_with_an_invalid_token(): void
    {
        $response = $this->postJson('/webhooks/uptimerobot/wrong-token', $this->downPayload());

        $response->assertStatus(403);
        $this->assertDatabaseCount('system_alerts', 0);
        Notification::assertNothingSent();
    }

    public function test_rejects_all_requests_when_no_token_is_configured(): void
    {
        config(['services.uptimerobot.webhook_token' => '']);

        // Chiar dacă cineva ar ghici un token gol/orice string, blank()
        // pe partea noastră trebuie să respingă mereu — fail closed.
        $response = $this->postJson('/webhooks/uptimerobot/anything', $this->downPayload());

        $response->assertStatus(403);
        $this->assertDatabaseCount('system_alerts', 0);
    }

    public function test_down_event_creates_a_critical_alert_and_notifies_super_admins(): void
    {
        $response = $this->postJson('/webhooks/uptimerobot/'.self::TOKEN, $this->downPayload());

        $response->assertStatus(200);

        $this->assertDatabaseHas('system_alerts', [
            'type' => 'downtime',
            'severity' => 'critical',
        ]);

        $alert = SystemAlert::sole();
        $this->assertFalse($alert->isResolved());
        $this->assertSame('12345', $alert->metadata['monitor_id']);
        $this->assertSame([$this->superAdmin->id], $alert->notified_users);

        Notification::assertSentTo(
            $this->superAdmin,
            UptimeRobotAlertNotification::class,
            fn (UptimeRobotAlertNotification $n) => $n->type === 'down' && $n->monitorFriendlyName === 'CRM AktivTherm'
        );
    }

    public function test_down_event_with_unknown_type_is_ignored(): void
    {
        $response = $this->postJson('/webhooks/uptimerobot/'.self::TOKEN, $this->downPayload([
            'alertTypeFriendlyName' => 'Paused',
        ]));

        $response->assertStatus(200);
        $this->assertDatabaseCount('system_alerts', 0);
        Notification::assertNothingSent();
    }

    public function test_up_event_resolves_the_matching_open_alert_and_notifies_super_admins(): void
    {
        $this->postJson('/webhooks/uptimerobot/'.self::TOKEN, $this->downPayload());

        $response = $this->postJson('/webhooks/uptimerobot/'.self::TOKEN, $this->upPayload([
            'alertDuration' => 600, // 10 minute
        ]));

        $response->assertStatus(200);

        $alert = SystemAlert::sole();
        $this->assertTrue($alert->isResolved());
        $this->assertSame(10, $alert->metadata['downtime_minutes']);

        Notification::assertSentTo(
            $this->superAdmin,
            UptimeRobotAlertNotification::class,
            fn (UptimeRobotAlertNotification $n) => $n->type === 'up' && $n->downtimeMinutes === 10
        );
    }

    public function test_up_event_without_a_matching_alert_still_notifies_but_creates_nothing(): void
    {
        $response = $this->postJson('/webhooks/uptimerobot/'.self::TOKEN, $this->upPayload());

        $response->assertStatus(200);
        $this->assertDatabaseCount('system_alerts', 0);

        Notification::assertSentTo(
            $this->superAdmin,
            UptimeRobotAlertNotification::class,
            fn (UptimeRobotAlertNotification $n) => $n->type === 'up'
        );
    }

    public function test_only_super_admins_are_notified(): void
    {
        $salesRep = User::factory()->create();
        $salesRep->assignRole('sales_rep');

        $this->postJson('/webhooks/uptimerobot/'.self::TOKEN, $this->downPayload());

        Notification::assertSentTo($this->superAdmin, UptimeRobotAlertNotification::class);
        Notification::assertNotSentTo($salesRep, UptimeRobotAlertNotification::class);
    }
}
