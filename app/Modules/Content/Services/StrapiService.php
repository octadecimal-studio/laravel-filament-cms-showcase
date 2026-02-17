<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serwis do komunikacji z Strapi CMS API.
 */
final class StrapiService
{
    private string $baseUrl;
    private ?string $token;

    public function __construct(?string $baseUrl = null, ?string $token = null)
    {
        $this->baseUrl = $baseUrl ?? config('strapi.api_url', 'http://203.0.113.10:1339');
        $this->token = $token ?? config('strapi.api_token');
    }

    /**
     * Sprawdza czy Strapi jest dostępny.
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/_health");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Strapi health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Tworzy wpis w Content Type.
     *
     * @param  string  $contentType Nazwa Content Type (np. 'motorcycles')
     * @param  array<string, mixed>  $data Dane do utworzenia
     * @return array<string, mixed>|null
     */
    public function createEntry(string $contentType, array $data): ?array
    {
        try {
            $url = "{$this->baseUrl}/api/{$contentType}";
            $headers = ['Content-Type' => 'application/json'];

            if ($this->token) {
                $headers['Authorization'] = "Bearer {$this->token}";
            }

            $response = Http::withHeaders($headers)
                ->post($url, ['data' => $data]);

            if (! $response->successful()) {
                $errorBody = $response->body();
                Log::error('Strapi create entry failed', [
                    'content_type' => $contentType,
                    'status' => $response->status(),
                    'error' => $errorBody,
                    'url' => $url,
                ]);

                // Jeśli to 404, Content Type może nie istnieć
                if ($response->status() === 404) {
                    throw new \RuntimeException("Content Type '{$contentType}' nie istnieje w Strapi. Najpierw zaimportuj schemat.");
                }

                return null;
            }

            $result = $response->json();

            return $result['data'] ?? null;
        } catch (\Exception $e) {
            Log::error('Strapi create entry exception', [
                'content_type' => $contentType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Pobiera wpisy z Content Type.
     *
     * @param  string  $contentType Nazwa Content Type
     * @param  array<string, mixed>  $params Parametry zapytania (populate, filters, etc.)
     * @return array<int, array<string, mixed>>
     */
    public function getEntries(string $contentType, array $params = []): array
    {
        try {
            $url = "{$this->baseUrl}/api/{$contentType}";
            $headers = [];

            if ($this->token) {
                $headers['Authorization'] = "Bearer {$this->token}";
            }

            $response = Http::withHeaders($headers)
                ->get($url, $params);

            if (! $response->successful()) {
                Log::error('Strapi get entries failed', [
                    'content_type' => $contentType,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return [];
            }

            $result = $response->json();

            return $result['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Strapi get entries exception', [
                'content_type' => $contentType,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Aktualizuje wpis w Content Type.
     *
     * @param  string  $contentType Nazwa Content Type
     * @param  int|string  $id ID wpisu
     * @param  array<string, mixed>  $data Dane do aktualizacji
     * @return array<string, mixed>|null
     */
    public function updateEntry(string $contentType, int|string $id, array $data): ?array
    {
        try {
            $url = "{$this->baseUrl}/api/{$contentType}/{$id}";
            $headers = ['Content-Type' => 'application/json'];

            if ($this->token) {
                $headers['Authorization'] = "Bearer {$this->token}";
            }

            $response = Http::withHeaders($headers)
                ->put($url, ['data' => $data]);

            if (! $response->successful()) {
                Log::error('Strapi update entry failed', [
                    'content_type' => $contentType,
                    'id' => $id,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return null;
            }

            $result = $response->json();

            return $result['data'] ?? null;
        } catch (\Exception $e) {
            Log::error('Strapi update entry exception', [
                'content_type' => $contentType,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Publikuje wpis (draftAndPublish).
     *
     * @param  string  $contentType Nazwa Content Type
     * @param  int|string  $id ID wpisu
     * @return bool
     */
    public function publishEntry(string $contentType, int|string $id): bool
    {
        try {
            $url = "{$this->baseUrl}/api/{$contentType}/{$id}/actions/publish";
            $headers = [];

            if ($this->token) {
                $headers['Authorization'] = "Bearer {$this->token}";
            }

            $response = Http::withHeaders($headers)->post($url);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Strapi publish entry exception', [
                'content_type' => $contentType,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
