<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Generator\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    /**
     * Register a deployed template
     *
     * Called automatically by deployment script after successful deployment.
     * Creates or updates Template record with webhook URL for revalidation.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
            ],
            'webhook_url' => 'required|url|max:500',
            'tenant_id' => [
                'required',
                'uuid',
                Rule::exists('tenants', 'id'),
                function ($attribute, $value, $fail) use ($request) {
                    // Ensure tenant_id matches current tenant from middleware
                    if ($value !== $request->tenant->id) {
                        $fail('Tenant ID must match the authenticated tenant.');
                    }
                },
            ],
            'deployment_env' => 'nullable|in:dev,prd,tst',
        ]);

        // Check if template already exists (update instead of create)
        $template = Template::where('slug', $validated['slug'])
            ->where('tenant_id', $request->tenant->id)
            ->first();

        if ($template) {
            // Update existing template
            $template->update([
                'webhook_url' => $validated['webhook_url'],
                'deployment_env' => $validated['deployment_env'] ?? $template->deployment_env,
            ]);

            Log::info('Template updated', [
                'template' => $template->slug,
                'tenant' => $request->tenant->id,
                'webhook_url' => $validated['webhook_url'],
            ]);

            return response()->json([
                'data' => $template->fresh(),
                'message' => 'Template updated successfully',
                'action' => 'updated',
            ], 200);
        }

        // Create new template
        $template = Template::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'webhook_url' => $validated['webhook_url'],
            'tenant_id' => $request->tenant->id,
            'deployment_env' => $validated['deployment_env'] ?? 'prd',
        ]);

        Log::info('Template registered', [
            'template' => $template->slug,
            'tenant' => $request->tenant->id,
            'webhook_url' => $validated['webhook_url'],
        ]);

        return response()->json([
            'data' => $template,
            'message' => 'Template registered successfully',
            'action' => 'created',
        ], 201);
    }

    /**
     * Test webhook connectivity
     *
     * Verifies that webhook URL is reachable and responds correctly.
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function testWebhook(string $slug): JsonResponse
    {
        $template = Template::where('slug', $slug)
            ->where('tenant_id', request()->tenant->id)
            ->first();

        if (! $template) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_NOT_FOUND',
                    'message' => "Template '$slug' not found for current tenant.",
                ],
            ], 404);
        }

        // Test connectivity to webhook test endpoint
        try {
            $testUrl = rtrim($template->webhook_url, '/').'/api/revalidate/test';
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($testUrl);

            return response()->json([
                'template' => $slug,
                'webhook_url' => $template->webhook_url,
                'test_url' => $testUrl,
                'status' => $response->status(),
                'response' => $response->json(),
                'connected' => $response->successful(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'template' => $slug,
                'webhook_url' => $template->webhook_url,
                'connected' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
