<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

/**
 * Health check endpoint untuk monitoring kesiapan produksi.
 *
 * Memeriksa koneksi database, queue, dan cache tanpa mengubah data.
 */
class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['ok']);

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['ok' => true, 'message' => 'Database terhubung.'];
        } catch (\Throwable $e) {
            Log::error('Health check database gagal: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Database tidak terhubung.'];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, 'ok', now()->addSeconds(10));
            $result = Cache::get($key) === 'ok';
            Cache::forget($key);

            return ['ok' => $result, 'message' => $result ? 'Cache berfungsi.' : 'Cache tidak merespon.'];
        } catch (\Throwable $e) {
            Log::error('Health check cache gagal: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Cache error.'];
        }
    }

    private function checkQueue(): array
    {
        try {
            // Queue connection default database — cek tabel jobs dapat diakses.
            DB::table('jobs')->limit(1)->get();

            return ['ok' => true, 'message' => 'Queue tabel dapat diakses.'];
        } catch (\Throwable $e) {
            Log::error('Health check queue gagal: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Queue tabel tidak dapat diakses.'];
        }
    }
}
