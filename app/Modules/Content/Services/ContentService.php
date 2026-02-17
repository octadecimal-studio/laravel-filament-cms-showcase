<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use App\Models\Site;
use App\Models\User;
use App\Modules\Content\Models\ContentPublished;
use App\Modules\Content\Models\ContentVersion;
use App\Modules\Content\Models\SiteContent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Serwis zarządzający treściami CMS.
 *
 * Obsługuje pobieranie i publikację kontentu,
 * współdzielony między API i Filament.
 */
final class ContentService
{
    public function __construct(
        private readonly ContentVersioningService $versioningService,
        private readonly ContentCacheService $cacheService,
    ) {}

    /**
     * Pobiera opublikowany kontent dla strony.
     *
     * @param Site $site Strona klienta
     * @param string $env Środowisko (staging|production)
     * @return Collection<int, SiteContent>
     */
    public function getSiteContent(Site $site, string $env = 'production'): Collection
    {
        // Sprawdź cache
        $cacheKey = $this->cacheService->buildKey($site, $env);
        $cached = $this->cacheService->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        // Pobierz opublikowany kontent
        $contents = SiteContent::query()
            ->where('site_id', $site->id)
            ->whereHas('publications', function ($query) use ($env) {
                $query->where('environment', $env);
            })
            ->with([
                'publications' => fn ($q) => $q->where('environment', $env),
                'publications.version',
                'children' => fn ($q) => $q->orderBy('order'),
            ])
            ->orderBy('order')
            ->get();

        // Cache result
        $this->cacheService->put($cacheKey, $contents);

        return $contents;
    }

    /**
     * Pobiera pojedynczą sekcję po slug.
     *
     * @param Site $site Strona klienta
     * @param string $slug Slug sekcji
     * @param string $env Środowisko (staging|production)
     * @return SiteContent|null
     */
    public function getSection(Site $site, string $slug, string $env = 'production'): ?SiteContent
    {
        return SiteContent::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->whereHas('publications', function ($query) use ($env) {
                $query->where('environment', $env);
            })
            ->with([
                'publications' => fn ($q) => $q->where('environment', $env),
                'publications.version',
            ])
            ->first();
    }

    /**
     * Pobiera wszystkie sekcje typu page.
     *
     * @param Site $site Strona klienta
     * @param string $env Środowisko
     * @return Collection<int, SiteContent>
     */
    public function getPages(Site $site, string $env = 'production'): Collection
    {
        return SiteContent::query()
            ->where('site_id', $site->id)
            ->where('type', 'page')
            ->whereHas('publications', function ($query) use ($env) {
                $query->where('environment', $env);
            })
            ->with([
                'publications' => fn ($q) => $q->where('environment', $env),
                'publications.version',
                'children' => fn ($q) => $q->orderBy('order'),
            ])
            ->orderBy('order')
            ->get();
    }

    /**
     * Publikuje kontent na środowisko.
     *
     * @param SiteContent $content Kontent do publikacji
     * @param string $env Środowisko (staging|production)
     * @param User|null $user Użytkownik publikujący
     * @param array<string, mixed>|null $notes Notatki do publikacji
     * @return ContentPublished
     *
     * @throws \RuntimeException Gdy brak wersji do publikacji
     */
    public function publishContent(
        SiteContent $content,
        string $env = 'production',
        ?User $user = null,
        ?array $notes = null
    ): ContentPublished {
        // Pobierz bieżącą wersję
        $currentVersion = $this->versioningService->getCurrentVersion($content);

        if ($currentVersion === null) {
            throw new \RuntimeException("Brak wersji do publikacji dla content ID: {$content->id}");
        }

        return DB::transaction(function () use ($content, $env, $currentVersion, $user, $notes) {
            // Upsert publikacji
            $published = ContentPublished::updateOrCreate(
                [
                    'content_id' => $content->id,
                    'environment' => $env,
                ],
                [
                    'version_id' => $currentVersion->id,
                    'published_at' => now(),
                    'published_by' => $user?->id,
                    'publish_notes' => $notes,
                    'auto_published' => $user === null,
                ]
            );

            // Aktualizuj status kontentu
            if ($content->status !== 'published') {
                $content->update([
                    'status' => 'published',
                    'published_at' => now(),
                ]);
            }

            // Invaliduj cache
            $this->cacheService->invalidate($content->site, $env);

            return $published;
        });
    }

    /**
     * Cofa publikację z danego środowiska.
     *
     * @param SiteContent $content Kontent
     * @param string $env Środowisko
     * @return bool
     */
    public function unpublishContent(SiteContent $content, string $env = 'production'): bool
    {
        $deleted = ContentPublished::query()
            ->where('content_id', $content->id)
            ->where('environment', $env)
            ->delete();

        if ($deleted > 0) {
            $this->cacheService->invalidate($content->site, $env);
        }

        return $deleted > 0;
    }

    /**
     * Przywraca kontent do konkretnej wersji na środowisku.
     *
     * @param SiteContent $content Kontent
     * @param string $versionId UUID wersji
     * @param string $env Środowisko
     * @param User|null $user Użytkownik
     * @return ContentPublished
     *
     * @throws \RuntimeException Gdy wersja nie istnieje
     */
    public function revertToVersion(
        SiteContent $content,
        string $versionId,
        string $env = 'production',
        ?User $user = null
    ): ContentPublished {
        $version = ContentVersion::find($versionId);

        if ($version === null) {
            throw new \RuntimeException("Wersja nie istnieje: {$versionId}");
        }

        return DB::transaction(function () use ($content, $env, $version, $user) {
            $published = ContentPublished::updateOrCreate(
                [
                    'content_id' => $content->id,
                    'environment' => $env,
                ],
                [
                    'version_id' => $version->id,
                    'published_at' => now(),
                    'published_by' => $user?->id,
                    'publish_notes' => ['reverted_to_version' => $version->version],
                    'auto_published' => false,
                ]
            );

            $this->cacheService->invalidate($content->site, $env);

            return $published;
        });
    }

    /**
     * Pobiera opublikowaną wersję kontentu dla środowiska.
     *
     * @param SiteContent $content Kontent
     * @param string $env Środowisko
     * @return ContentVersion|null
     */
    public function getPublishedVersion(SiteContent $content, string $env = 'production'): ?ContentVersion
    {
        $published = ContentPublished::query()
            ->where('content_id', $content->id)
            ->where('environment', $env)
            ->with('version')
            ->first();

        return $published?->version;
    }

    /**
     * Sprawdza czy kontent jest opublikowany na danym środowisku.
     *
     * @param SiteContent $content Kontent
     * @param string $env Środowisko
     * @return bool
     */
    public function isPublished(SiteContent $content, string $env = 'production'): bool
    {
        return ContentPublished::query()
            ->where('content_id', $content->id)
            ->where('environment', $env)
            ->exists();
    }

    /**
     * Tworzy nowy kontent dla strony.
     *
     * @param Site $site Strona
     * @param array<string, mixed> $data Dane kontentu
     * @param User|null $user Użytkownik tworzący
     * @return SiteContent
     */
    public function createContent(Site $site, array $data, ?User $user = null): SiteContent
    {
        $content = SiteContent::create([
            'site_id' => $site->id,
            'tenant_id' => $site->customer?->tenant_id ?? $data['tenant_id'] ?? null,
            ...$data,
        ]);

        // Utwórz pierwszą wersję
        $this->versioningService->createVersion($content, $content->data ?? [], $user);

        return $content;
    }

    /**
     * Aktualizuje kontent.
     *
     * @param SiteContent $content Kontent do aktualizacji
     * @param array<string, mixed> $data Nowe dane
     * @param User|null $user Użytkownik
     * @param string|null $changeSummary Opis zmian
     * @return SiteContent
     */
    public function updateContent(
        SiteContent $content,
        array $data,
        ?User $user = null,
        ?string $changeSummary = null
    ): SiteContent {
        $content->update($data);

        // Utwórz nową wersję
        $this->versioningService->createVersion(
            $content,
            $content->data ?? [],
            $user,
            $changeSummary
        );

        return $content->fresh();
    }
}
