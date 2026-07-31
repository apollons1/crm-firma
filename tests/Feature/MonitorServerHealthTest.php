<?php

namespace Tests\Feature;

use App\Console\Commands\MonitorServerHealth;
use App\Models\User;
use App\Notifications\ServerHealthAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

/**
 * Testează App\Console\Commands\MonitorServerHealth — pragurile de alertare
 * (spațiu liber pe disc < 5GB, coadă failed_jobs > 50) și deduplicarea
 * alertelor. Rulează comanda direct printr-un CommandTester, nu prin
 * $this->artisan(), ca să putem suprascrie freeDiskSpaceBytes() (spațiul
 * real de pe disc al mașinii care rulează testele nu poate fi controlat).
 */
class MonitorServerHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        Notification::fake();
    }

    private function runCommand(int $freeDiskBytes): void
    {
        $command = new class($freeDiskBytes) extends MonitorServerHealth
        {
            private int $freeBytes;

            public function __construct(int $freeBytes)
            {
                parent::__construct();

                $this->freeBytes = $freeBytes;
            }

            protected function freeDiskSpaceBytes(): int|false
            {
                return $this->freeBytes;
            }
        };

        $command->setLaravel($this->app);

        (new CommandTester($command))->execute([]);
    }

    private function seedFailedJobs(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'sync',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'test',
                'failed_at' => now(),
            ]);
        }
    }

    private function ampleFreeDiskBytes(): int
    {
        return (int) (50 * 1024 ** 3);
    }

    public function test_alerts_when_free_disk_space_is_below_five_gb(): void
    {
        $this->runCommand(freeDiskBytes: (int) (4 * 1024 ** 3));

        Notification::assertSentTo(
            $this->superAdmin,
            ServerHealthAlertNotification::class,
            fn (ServerHealthAlertNotification $n) => $n->type === 'low_disk_space' && $n->freeSpaceGb < 5.0
        );
    }

    public function test_does_not_alert_when_free_disk_space_is_above_five_gb(): void
    {
        $this->runCommand(freeDiskBytes: $this->ampleFreeDiskBytes());

        Notification::assertNotSentTo($this->superAdmin, ServerHealthAlertNotification::class);
    }

    public function test_alerts_when_failed_jobs_exceed_fifty(): void
    {
        $this->seedFailedJobs(51);

        $this->runCommand(freeDiskBytes: $this->ampleFreeDiskBytes());

        Notification::assertSentTo(
            $this->superAdmin,
            ServerHealthAlertNotification::class,
            fn (ServerHealthAlertNotification $n) => $n->type === 'failed_jobs_backlog' && $n->failedJobsCount === 51
        );
    }

    public function test_does_not_alert_when_failed_jobs_are_at_or_below_fifty(): void
    {
        $this->seedFailedJobs(50);

        $this->runCommand(freeDiskBytes: $this->ampleFreeDiskBytes());

        Notification::assertNotSentTo($this->superAdmin, ServerHealthAlertNotification::class);
    }

    public function test_repeated_runs_within_the_dedupe_window_do_not_duplicate_the_alert(): void
    {
        $this->runCommand(freeDiskBytes: (int) (4 * 1024 ** 3));
        $this->runCommand(freeDiskBytes: (int) (4 * 1024 ** 3));

        Notification::assertSentToTimes($this->superAdmin, ServerHealthAlertNotification::class, 1);
    }
}
