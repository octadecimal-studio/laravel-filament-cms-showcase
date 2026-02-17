<?php

namespace App\Services;

use App\Modules\Generator\Models\Template;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for triggering template revalidation webhooks.
 *
 * Sends webhook requests to deployed templates to trigger
 * cache revalidation when content is updated in CMS.
 */
class TemplateRevalidationService
{
    /**
     * Revalidate template cache for given tags.
     *
     * @param string $templateSlug Template slug
     * @param array<string> $tags Tags to revalidate (e.g. ['hero', 'content'])
     * @param string|null $path Optional path to revalidate
     * @param string|null $tenantId Optional tenant ID for explicit multi-tenancy security
     * @return bool Success status
     */
    public function revalidate(string $templateSlug, array $tags = ['content'], ?string $path = null, ?string $tenantId = null): bool
    {
        // Explicit tenant filtering for multi-tenancy security
        // Even though Template uses BelongsToTenant trait, we ensure tenant isolation here
        $query = Template::where('slug', $templateSlug);
        
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        
        $template = $query->first();

        if (! $template || ! $template->webhook_url) {
            Log::warning('No webhook configured for template', [
                'template' => $templateSlug,
                'tenant_id' => $tenantId,
            ]);

            return false;
        }

        $secret = config('services.revalidation.secret', env('REVALIDATE_SECRET'));
        $webhookUrl = rtrim($template->webhook_url, '/').'/api/revalidate';

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Revalidate-Secret' => $secret,
                    'Content-Type' => 'application/json',
                ])
                ->post($webhookUrl, [
                    'tags' => $tags,
                    'path' => $path,
                    'timestamp' => now()->toIso8601String(),
                ]);

            if ($response->successful()) {
                Log::info('Revalidation successful', [
                    'template' => $templateSlug,
                    'tenant_id' => $tenantId ?? $template->tenant_id,
                    'tags' => $tags,
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::warning('Revalidation failed', [
                'template' => $templateSlug,
                'tenant_id' => $tenantId ?? $template->tenant_id,
                'tags' => $tags,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Revalidation error', [
                'template' => $templateSlug,
                'tenant_id' => $tenantId ?? $template->tenant_id ?? null,
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Revalidate all tags for a template.
     *
     * @param string $templateSlug Template slug
     * @param string|null $tenantId Optional tenant ID for explicit multi-tenancy security
     * @return bool Success status
     */
    public function revalidateAll(string $templateSlug, ?string $tenantId = null): bool
    {
        return $this->revalidate($templateSlug, ['all'], '/', $tenantId);
    }
}
