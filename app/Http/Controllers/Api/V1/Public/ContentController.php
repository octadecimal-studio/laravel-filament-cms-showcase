<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GetContentRequest;
use App\Http\Resources\Api\V1\ContentResource;
use App\Models\Site;
use App\Modules\Content\Services\ContentService;
use Illuminate\Http\JsonResponse;

/**
 * Controller API dla kontentu strony.
 *
 * Obsługuje pobieranie treści dla frontendów Next.js.
 */
final class ContentController extends Controller
{
    public function __construct(
        private readonly ContentService $contentService,
    ) {}

    /**
     * Pobiera kontent strony dla danego środowiska.
     *
     * GET /api/v1/sites/{slug}/content
     *
     * @param GetContentRequest $request
     * @param Site $site
     * @return JsonResponse
     */
    public function show(GetContentRequest $request, Site $site): JsonResponse
    {
        $env = $request->validated('env', 'production');

        $contents = $this->contentService->getSiteContent($site, $env);

        // Buduj strukturę odpowiedzi
        $response = $this->buildContentResponse($site, $contents, $env);

        return response()->json($response);
    }

    /**
     * Pobiera pojedynczą sekcję po slug.
     *
     * GET /api/v1/sites/{slug}/content/{section}
     *
     * @param GetContentRequest $request
     * @param Site $site
     * @param string $section
     * @return JsonResponse
     */
    public function section(GetContentRequest $request, Site $site, string $section): JsonResponse
    {
        $env = $request->validated('env', 'production');

        $content = $this->contentService->getSection($site, $section, $env);

        if ($content === null) {
            return response()->json([
                'error' => 'Section not found',
                'section' => $section,
            ], 404);
        }

        return response()->json([
            'data' => new ContentResource($content),
        ]);
    }

    /**
     * Buduje strukturę odpowiedzi dla kontentu.
     *
     * @param Site $site
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Content\Models\SiteContent> $contents
     * @param string $env
     * @return array<string, mixed>
     */
    private function buildContentResponse(Site $site, $contents, string $env): array
    {
        // Site info z settings
        $siteSettings = $site->settings ?? [];

        // Grupuj treści po typie/slug
        $sections = [];
        foreach ($contents as $content) {
            $slug = $content->slug ?? $content->type;
            
            // Pobierz dane z opublikowanej wersji
            $publication = $content->publications->first();
            $data = $publication?->version?->data ?? $content->data ?? [];

            $sections[$slug] = $data;
        }

        return [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'tagline' => $siteSettings['tagline'] ?? null,
                'logo' => $siteSettings['logo'] ?? null,
                'favicon' => $siteSettings['favicon'] ?? null,
                'environment' => $env,
            ],
            'navigation' => $sections['navigation'] ?? $this->getDefaultNavigation($site),
            'sections' => $this->filterSections($sections),
            'footer' => $sections['footer'] ?? $this->getDefaultFooter($site),
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'cache_ttl' => 3600,
            ],
        ];
    }

    /**
     * Filtruje sekcje usuwając navigation i footer.
     *
     * @param array<string, mixed> $sections
     * @return array<string, mixed>
     */
    private function filterSections(array $sections): array
    {
        $excluded = ['navigation', 'footer', 'site', 'meta'];
        
        return array_filter(
            $sections,
            fn (string $key) => ! in_array($key, $excluded),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Domyślna nawigacja gdy brak w kontencie.
     *
     * @param Site $site
     * @return array<string, mixed>
     */
    private function getDefaultNavigation(Site $site): array
    {
        return [
            'links' => [],
            'cta' => null,
        ];
    }

    /**
     * Domyślny footer gdy brak w kontencie.
     *
     * @param Site $site
     * @return array<string, mixed>
     */
    private function getDefaultFooter(Site $site): array
    {
        return [
            'copyright' => "© " . date('Y') . " {$site->name}",
            'links' => [],
        ];
    }
}
