<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;

/**
 * Endpoint PUBLIC (fără autentificare) folosit de UptimeRobot pentru
 * monitorizare externă. Rezultatul e cache-uit 30s ca să nu suprasolicite
 * verificările (Stripe/Backblaze presupun apeluri de rețea reale).
 *
 * Cache-ul folosește explicit driverul "file", NU cache-ul implicit al
 * aplicației (Redis în producție) — altfel, dacă Redis pică, chiar citirea
 * din cache a health check-ului ar arunca excepție înainte să apucăm să
 * raportăm "Redis: indisponibil".
 */
class HealthController extends Controller
{
    private const CACHE_KEY = 'health-check:result';

    private const CACHE_SECONDS = 30;

    private const BACKUP_MAX_AGE_HOURS = 25;

    public function __invoke(): JsonResponse
    {
        $result = $this->cachedResult();

        return response()->json($result['body'], $result['status']);
    }

    /**
     * @return array{status: int, body: array}
     */
    private function cachedResult(): array
    {
        try {
            return Cache::store('file')->remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->runChecks());
        } catch (Throwable $e) {
            // Cache-ul pe disc a eșuat (ex: storage neinscriptibil) — rulăm
            // verificările direct, fără memoizare, în loc să crăpăm 500.
            return $this->runChecks();
        }
    }

    /**
     * @return array{status: int, body: array}
     */
    private function runChecks(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'backup' => $this->checkBackup(),
            'stripe' => $this->checkStripe(),
        ];

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        return [
            'status' => $healthy ? 200 : 503,
            'body' => [
                'status' => $healthy ? 'ok' : 'error',
                'checks' => $checks,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Conexiune bază de date indisponibilă.'];
        }
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Conexiune Redis indisponibilă.'];
        }
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function checkStorage(): array
    {
        try {
            $path = storage_path('framework/health-check.tmp');
            file_put_contents($path, (string) now()->timestamp);
            $writable = file_exists($path);
            @unlink($path);

            return $writable
                ? ['ok' => true]
                : ['ok' => false, 'message' => 'Storage nu este inscriptibil.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Storage nu este inscriptibil.'];
        }
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function checkBackup(): array
    {
        try {
            $backupName = config('backup.backup.name');
            $diskNames = data_get(config('backup.monitor_backups'), '0.disks', ['local']);

            $newestBackupDate = null;

            foreach ($diskNames as $diskName) {
                $backup = BackupDestination::create($diskName, $backupName)->newestBackup();

                if ($backup && (! $newestBackupDate || $backup->date()->gt($newestBackupDate))) {
                    $newestBackupDate = $backup->date();
                }
            }

            if ($newestBackupDate === null) {
                return ['ok' => false, 'message' => 'Niciun backup găsit.'];
            }

            if ($newestBackupDate->lt(now()->subHours(self::BACKUP_MAX_AGE_HOURS))) {
                return [
                    'ok' => false,
                    'message' => 'Ultimul backup e mai vechi de '.self::BACKUP_MAX_AGE_HOURS." ore ({$newestBackupDate->diffForHumans()}).",
                ];
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Nu s-a putut verifica starea backup-ului.'];
        }
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function checkStripe(): array
    {
        try {
            app(StripeService::class)->ping();

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Stripe API nu răspunde.'];
        }
    }
}
