<?php

namespace Tests\Feature;

use App\Services\StripeService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Testează GET /health — endpoint public folosit de UptimeRobot. Stripe și
 * Redis sunt mockate (nu vrem apeluri reale de rețea în teste); backup-ul
 * e controlat prin Storage::fake('local') (Spatie citește data din numele
 * fișierului sau, dacă nu se potrivește, din lastModified()).
 */
class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rezultatul e cache-uit 30s pe driverul "file" — golim explicit,
        // altfel un test anterior "contaminează" următorul.
        Cache::store('file')->forget('health-check:result');
    }

    private function mockStripe(bool $healthy): void
    {
        $stripe = Mockery::mock(StripeService::class);

        if ($healthy) {
            $stripe->shouldReceive('ping')->once()->andReturnNull();
        } else {
            $stripe->shouldReceive('ping')->once()->andThrow(new Exception('Stripe indisponibil'));
        }

        $this->app->instance(StripeService::class, $stripe);
    }

    private function mockRedis(bool $healthy): void
    {
        $connection = Mockery::mock();

        if ($healthy) {
            $connection->shouldReceive('ping')->andReturnTrue();
        } else {
            $connection->shouldReceive('ping')->andThrow(new Exception('Redis indisponibil'));
        }

        Redis::shouldReceive('connection')->andReturn($connection);
    }

    private function putFreshBackup(): void
    {
        Storage::fake('local');

        $filename = now()->format('Y-m-d-H-i-s').'.zip';
        Storage::disk('local')->put(config('backup.backup.name')."/{$filename}", 'fake-backup');
    }

    private function putStaleBackup(): void
    {
        Storage::fake('local');

        $filename = now()->subHours(30)->format('Y-m-d-H-i-s').'.zip';
        Storage::disk('local')->put(config('backup.backup.name')."/{$filename}", 'fake-backup');
    }

    public function test_returns_200_and_ok_when_all_checks_pass(): void
    {
        $this->mockStripe(healthy: true);
        $this->mockRedis(healthy: true);
        $this->putFreshBackup();

        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
        $response->assertJsonPath('checks.database.ok', true);
        $response->assertJsonPath('checks.redis.ok', true);
        $response->assertJsonPath('checks.storage.ok', true);
        $response->assertJsonPath('checks.backup.ok', true);
        $response->assertJsonPath('checks.stripe.ok', true);
    }

    public function test_returns_503_when_stripe_is_down(): void
    {
        $this->mockStripe(healthy: false);
        $this->mockRedis(healthy: true);
        $this->putFreshBackup();

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $response->assertJson(['status' => 'error']);
        $response->assertJsonPath('checks.stripe.ok', false);
        $response->assertJsonPath('checks.database.ok', true);
    }

    public function test_returns_503_when_redis_is_down(): void
    {
        $this->mockStripe(healthy: true);
        $this->mockRedis(healthy: false);
        $this->putFreshBackup();

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $response->assertJsonPath('checks.redis.ok', false);
    }

    public function test_returns_503_when_the_newest_backup_is_older_than_25_hours(): void
    {
        $this->mockStripe(healthy: true);
        $this->mockRedis(healthy: true);
        $this->putStaleBackup();

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $response->assertJsonPath('checks.backup.ok', false);
    }

    public function test_returns_503_when_no_backup_exists(): void
    {
        $this->mockStripe(healthy: true);
        $this->mockRedis(healthy: true);
        Storage::fake('local');

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $response->assertJsonPath('checks.backup.ok', false);
    }

    public function test_result_is_cached_for_30_seconds(): void
    {
        $this->mockStripe(healthy: true);
        $this->mockRedis(healthy: true);
        $this->putFreshBackup();

        $this->getJson('/health')->assertStatus(200);

        // Mock-ul Stripe are ->once() — dacă a doua cerere ar re-rula
        // verificările (adică nu ar folosi cache-ul), Mockery ar eșua aici.
        $this->getJson('/health')->assertStatus(200);
    }
}
