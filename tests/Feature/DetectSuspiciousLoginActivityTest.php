<?php

namespace Tests\Feature;

use App\Models\FailedLoginAttempt;
use App\Models\User;
use App\Notifications\SuspiciousLoginActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Testează comanda security:detect-suspicious-logins — pragurile de
 * detecție (brute-force per IP, credential stuffing per email), deduplicarea
 * alertelor în aceeași fereastră, și faptul că notifică doar super_admin.
 */
class DetectSuspiciousLoginActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::create(['name' => 'sales_rep', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        User::factory()->create()->assignRole('sales_rep');

        Notification::fake();
    }

    private function seedAttempts(string $ip, ?string $email, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            FailedLoginAttempt::create([
                'ip_address' => $ip,
                'email_attempted' => $email,
                'user_agent' => 'TestAgent',
                'attempted_at' => now(),
            ]);
        }
    }

    public function test_ip_with_fifty_or_more_failed_attempts_triggers_brute_force_alert(): void
    {
        $this->seedAttempts('198.51.100.1', 'victima@example.com', 50);

        $this->artisan('security:detect-suspicious-logins')->assertSuccessful();

        Notification::assertSentTo(
            $this->superAdmin,
            SuspiciousLoginActivityNotification::class,
            fn (SuspiciousLoginActivityNotification $notification) => $notification->type === 'brute_force'
                && $notification->identifier === '198.51.100.1'
                && $notification->count === 50
        );
    }

    public function test_ip_with_fewer_than_fifty_attempts_does_not_trigger_an_alert(): void
    {
        $this->seedAttempts('198.51.100.2', 'victima@example.com', 49);

        $this->artisan('security:detect-suspicious-logins')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_email_tried_from_twenty_or_more_distinct_ips_triggers_credential_stuffing_alert(): void
    {
        for ($i = 0; $i < 20; $i++) {
            FailedLoginAttempt::create([
                'ip_address' => "203.0.113.{$i}",
                'email_attempted' => 'tinta@example.com',
                'user_agent' => 'TestAgent',
                'attempted_at' => now(),
            ]);
        }

        $this->artisan('security:detect-suspicious-logins')->assertSuccessful();

        Notification::assertSentTo(
            $this->superAdmin,
            SuspiciousLoginActivityNotification::class,
            fn (SuspiciousLoginActivityNotification $notification) => $notification->type === 'credential_stuffing'
                && $notification->identifier === 'tinta@example.com'
                && $notification->count === 20
        );
    }

    public function test_attempts_outside_the_thirty_minute_window_are_ignored(): void
    {
        FailedLoginAttempt::insert(array_map(fn () => [
            'ip_address' => '198.51.100.3',
            'email_attempted' => 'victima@example.com',
            'user_agent' => 'TestAgent',
            'attempted_at' => now()->subMinutes(31),
        ], range(1, 60)));

        $this->artisan('security:detect-suspicious-logins')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_repeated_runs_within_the_window_do_not_duplicate_the_alert(): void
    {
        $this->seedAttempts('198.51.100.4', 'victima@example.com', 50);

        $this->artisan('security:detect-suspicious-logins')->assertSuccessful();
        $this->artisan('security:detect-suspicious-logins')->assertSuccessful();

        Notification::assertSentToTimes($this->superAdmin, SuspiciousLoginActivityNotification::class, 1);
    }

    public function test_only_super_admins_are_notified(): void
    {
        $this->seedAttempts('198.51.100.5', 'victima@example.com', 50);

        $this->artisan('security:detect-suspicious-logins')->assertSuccessful();

        $salesRep = User::role('sales_rep')->sole();
        Notification::assertNotSentTo($salesRep, SuspiciousLoginActivityNotification::class);
    }
}
