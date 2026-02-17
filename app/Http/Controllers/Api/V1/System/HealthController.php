<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Controller dla health checks.
 *
 * Obsługuje sprawdzanie stanu systemu.
 */
final class HealthController extends Controller
{
    /**
     * Sprawdza stan systemu.
     *
     * GET /api/v1/health
     *
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'app' => $this->checkApp(),
        ];

        $allHealthy = ! in_array(false, array_column($checks, 'healthy'), true);
        $status = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks,
        ], $status);
    }

    /**
     * Sprawdza połączenie z bazą danych.
     *
     * @return array{healthy: bool, message: string, latency_ms: float|null}
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = (microtime(true) - $start) * 1000;

            return [
                'healthy' => true,
                'message' => 'Database connection OK',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * Sprawdza cache.
     *
     * @return array{healthy: bool, message: string, driver: string}
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, true, 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value !== true) {
                return [
                    'healthy' => false,
                    'message' => 'Cache read/write failed',
                    'driver' => config('cache.default'),
                ];
            }

            return [
                'healthy' => true,
                'message' => 'Cache OK',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Cache check failed: ' . $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }
    }

    /**
     * Sprawdza aplikację.
     *
     * @return array{healthy: bool, message: string, php_version: string, laravel_version: string}
     */
    private function checkApp(): array
    {
        return [
            'healthy' => true,
            'message' => 'Application running',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }
}
