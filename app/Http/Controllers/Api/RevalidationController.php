<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Generator\Models\Template;
use App\Services\TemplateRevalidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for manual revalidation testing.
 *
 * Allows triggering revalidation webhooks manually for testing purposes.
 */
class RevalidationController extends Controller
{
    public function __construct(
        private TemplateRevalidationService $revalidation
    ) {
    }

    /**
     * Manually trigger revalidation for a template.
     *
     * Useful for testing webhook connectivity and revalidation flow.
     *
     * @param Request $request
     * @param string $slug Template slug
     * @return JsonResponse
     */
    public function trigger(Request $request, string $slug): JsonResponse
    {
        $template = Template::where('slug', $slug)
            ->where('tenant_id', $request->tenant->id)
            ->first();

        if (! $template) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_NOT_FOUND',
                    'message' => "Template '$slug' not found for current tenant.",
                ],
            ], 404);
        }

        $tags = $request->input('tags', ['content']);
        $path = $request->input('path', '/');

        if (! is_array($tags)) {
            $tags = [$tags];
        }

        $success = $this->revalidation->revalidate($slug, $tags, $path);

        return response()->json([
            'template' => $slug,
            'webhook_url' => $template->webhook_url,
            'tags' => $tags,
            'path' => $path,
            'success' => $success,
            'timestamp' => now()->toIso8601String(),
        ], $success ? 200 : 500);
    }
}
