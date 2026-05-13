<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateRevalidationService
{
    public function revalidate(string $templateSlug, array $tags = [], mixed $webhookUrl = null, ?string $tenantId = null): bool
    {
        if (empty($webhookUrl)) {
            return false;
        }

        try {
            $response = Http::timeout(5)->post((string) $webhookUrl, [
                'slug' => $templateSlug,
                'tags' => $tags,
                'tenant_id' => $tenantId,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Template revalidation failed', [
                'slug' => $templateSlug,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
