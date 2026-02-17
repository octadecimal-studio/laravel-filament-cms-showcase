<?php

namespace App\Http\Middleware;

use App\Modules\Core\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware identyfikujący tenant z requestu.
 *
 * Sprawdza w kolejności:
 * 1. Header X-Tenant-ID (najwyższy priorytet)
 * 2. Query parameter ?tenant_id
 * 3. Subdomain {tenant}.octadecimal.studio
 *
 * Ustawia tenant w kontenerze aplikacji jako 'current_tenant'.
 */
class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Publiczne endpointy bez wymogu tenant
        // - OpenAPI spec
        // - MotoRent API (ma własną logikę identyfikacji tenanta w kontrolerze)
        // - Plugin API (identyfikacja przez {site} parameter w URL)
        if ($request->is('api/openapi')
            || $request->is('api-docs')
            || $request->is('api/motorent/*')
            || $request->is('api/motorent')
            || $request->is('api/v1/health')
            || $request->is('api/v1/sites/*')
            || $request->is('api/menus/*')
        ) {
            return $next($request);
        }

        // Priority 1: Header X-Tenant-ID
        $tenantId = $request->header('X-Tenant-ID');

        // Priority 2: Query parameter
        if (! $tenantId) {
            $tenantId = $request->query('tenant_id');
        }

        // Priority 3: Subdomain extraction
        if (! $tenantId) {
            $tenantId = $this->extractFromSubdomain($request);
        }

        // If no tenant ID found, return error
        if (! $tenantId) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_REQUIRED',
                    'message' => 'Tenant ID is required. Provide via X-Tenant-ID header, ?tenant_id query, or subdomain.',
                    'details' => [
                        'hint' => 'Use X-Tenant-ID header for explicit identification',
                        'examples' => [
                            'header' => 'X-Tenant-ID: {uuid}',
                            'query' => '?tenant_id={uuid}',
                            'subdomain' => '{tenant}.octadecimal.studio',
                        ],
                    ],
                ],
            ], 400);
        }

        // Resolve tenant from cache or database
        $tenant = Cache::remember("tenant:$tenantId", 3600, function () use ($tenantId) {
            return Tenant::find($tenantId);
        });

        // If tenant not found, return 404
        if (! $tenant) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => "Tenant with ID '$tenantId' not found.",
                    'details' => [
                        'tenant_id' => $tenantId,
                    ],
                ],
            ], 404);
        }

        // Check if tenant is active
        if (! $tenant->is_active) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_INACTIVE',
                    'message' => "Tenant '$tenant->name' is inactive.",
                    'details' => [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                    ],
                ],
            ], 403);
        }

        // Set tenant in application container
        app()->instance('current_tenant', $tenant);
        config(['app.current_tenant_id' => $tenant->id]);

        // Attach tenant to request for easy access
        $request->merge(['tenant' => $tenant]);

        return $next($request);
    }

    /**
     * Extract tenant ID from subdomain.
     *
     * Pattern: {tenant}.octadecimal.studio
     */
    private function extractFromSubdomain(Request $request): ?string
    {
        $host = $request->getHost();

        // Pattern: {tenant}.octadecimal.studio
        if (preg_match('/^([a-z0-9-]+)\.octadecimal\.studio$/', $host, $matches)) {
            $subdomain = $matches[1];

            // Cache subdomain -> tenant_id mapping
            return Cache::remember("tenant:subdomain:$subdomain", 3600, function () use ($subdomain) {
                return Tenant::where('subdomain', $subdomain)
                    ->orWhere('slug', $subdomain)
                    ->value('id');
            });
        }

        return null;
    }
}
