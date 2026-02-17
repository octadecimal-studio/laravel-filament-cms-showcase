<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\Content\Services\ContentCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Controller dla webhooków rewalidacji.
 *
 * Obsługuje żądania rewalidacji cache z CMS.
 */
final class RevalidationController extends Controller
{
    public function __construct(
        private readonly ContentCacheService $cacheService,
    ) {}

    /**
     * Obsługuje żądanie rewalidacji.
     *
     * POST /api/v1/webhooks/revalidate
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        // Walidacja secret
        $secret = $request->header('X-Revalidate-Secret');
        $expectedSecret = config('services.revalidation.secret');

        if ($secret !== $expectedSecret) {
            Log::warning('Revalidation webhook: Invalid secret', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Unauthorized',
            ], 401);
        }

        // Walidacja body
        $validated = $request->validate([
            'site_id' => 'required|uuid|exists:sites,id',
            'tags' => 'sometimes|array',
            'tags.*' => 'string',
            'path' => 'sometimes|string',
            'environment' => 'sometimes|in:staging,production',
        ]);

        $site = Site::find($validated['site_id']);

        if ($site === null) {
            return response()->json([
                'error' => 'Site not found',
            ], 404);
        }

        $env = $validated['environment'] ?? 'production';
        $tags = $validated['tags'] ?? ['content'];
        $path = $validated['path'] ?? null;

        // Inwaliduj cache wewnętrzny
        $this->cacheService->invalidate($site, $env);

        // Wywołaj rewalidację w Next.js (jeśli URL skonfigurowany)
        $revalidated = $this->triggerNextJsRevalidation($site, $env, $tags, $path);

        Log::info('Revalidation webhook processed', [
            'site_id' => $site->id,
            'environment' => $env,
            'tags' => $tags,
            'path' => $path,
            'nextjs_revalidated' => $revalidated,
        ]);

        return response()->json([
            'success' => true,
            'site_id' => $site->id,
            'environment' => $env,
            'tags' => $tags,
            'nextjs_revalidated' => $revalidated,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Wywołuje rewalidację w Next.js.
     *
     * @param Site $site
     * @param string $env
     * @param array<string> $tags
     * @param string|null $path
     * @return bool
     */
    private function triggerNextJsRevalidation(
        Site $site,
        string $env,
        array $tags,
        ?string $path
    ): bool {
        // Pobierz URL środowiska
        $baseUrl = $env === 'production'
            ? $site->production_url
            : $site->staging_url;

        if (empty($baseUrl)) {
            return false;
        }

        // Next.js revalidation endpoint
        $revalidateUrl = rtrim($baseUrl, '/') . '/api/revalidate';
        $revalidateSecret = config('services.nextjs.revalidate_secret');

        if (empty($revalidateSecret)) {
            Log::warning('Next.js revalidate secret not configured');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Revalidate-Secret' => $revalidateSecret,
                ])
                ->post($revalidateUrl, [
                    'tags' => $tags,
                    'path' => $path,
                ]);

            if ($response->successful()) {
                Log::info('Next.js revalidation successful', [
                    'site_id' => $site->id,
                    'url' => $revalidateUrl,
                ]);
                return true;
            }

            Log::warning('Next.js revalidation failed', [
                'site_id' => $site->id,
                'url' => $revalidateUrl,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Next.js revalidation exception', [
                'site_id' => $site->id,
                'url' => $revalidateUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
