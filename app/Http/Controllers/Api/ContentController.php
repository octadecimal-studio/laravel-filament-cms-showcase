<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Content\Models\ContentBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\PathItem(
 *     path="/v1/content"
 * )
 */
class ContentController extends Controller
{
    /**
     * Get content blocks for a template
     *
     * @OA\Get(
     *     path="/v1/content",
     *     operationId="getContent",
     *     tags={"Content"},
     *     summary="Get content blocks for a template",
     *     description="Tenant is identified via X-Tenant-ID header, ?tenant_id query, or subdomain",
     *     @OA\Parameter(
     *         name="X-Tenant-ID",
     *         in="header",
     *         required=false,
     *         description="Tenant UUID (highest priority)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         required=false,
     *         description="Tenant UUID (fallback if header not provided)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="template",
     *         in="query",
     *         required=false,
     *         description="Template slug (e.g. bar-mobilny)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="section",
     *         in="query",
     *         required=false,
     *         description="Content section (hero, gallery, testimonials, contact)",
     *         @OA\Schema(type="string", enum={"hero", "gallery", "testimonials", "contact"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Content blocks",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/ContentBlock")
     *             ),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tenant not found"),
     *     @OA\Response(response=429, description="Rate limit exceeded")
     * )
     *
     * @param Request $request
     * @param string $tenant
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Tenant is already identified by middleware and attached to request
        $tenant = $request->tenant;

        $query = ContentBlock::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->when($request->filled('category'), function ($q) use ($request) {
                // Filtruj po kategorii (hero, features, cta, etc.)
                $q->where('category', $request->category);
            })
            ->when($request->filled('slug'), function ($q) use ($request) {
                // Pobierz konkretny blok po slug
                $q->where('slug', $request->slug);
            })
            ->orderBy('category')
            ->orderBy('name');

        $content = $query->get();

        return response()->json([
            'data' => $content->map(function ($block) {
                return [
                    'id' => $block->id,
                    'name' => $block->name,
                    'slug' => $block->slug,
                    'category' => $block->category,
                    'description' => $block->description,
                    'schema' => $block->schema,
                    'default_data' => $block->default_data,
                    'data' => $block->default_data, // Dla kompatybilności - używa default_data jako aktualne dane
                ];
            }),
            'meta' => [
                'count' => $content->count(),
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'category' => $request->query('category'),
                'slug' => $request->query('slug'),
            ],
        ]);
    }
}
