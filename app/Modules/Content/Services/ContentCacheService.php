<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use App\Models\Site;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Serwis zarządzający cache dla kontentu.
 *
 * Obsługuje cache'owanie odpowiedzi API i inwalidację.
 * Używa Redis gdy dostępny, fallback na file cache.
 */
final class ContentCacheService
{
    /**
     * Prefix dla kluczy cache.
     */
    private const CACHE_PREFIX = 'content:';

    /**
     * Domyślny TTL w sekundach (1 godzina).
     */
    private const DEFAULT_TTL = 3600;

    /**
     * Buduje klucz cache dla strony i środowiska.
     *
     * @param Site $site Strona
     * @param string $env Środowisko
     * @return string
     */
    public function buildKey(Site $site, string $env = 'production'): string
    {
        return self::CACHE_PREFIX . "site:{$site->id}:env:{$env}";
    }

    /**
     * Buduje klucz cache dla pojedynczej sekcji.
     *
     * @param Site $site Strona
     * @param string $slug Slug sekcji
     * @param string $env Środowisko
     * @return string
     */
    public function buildSectionKey(Site $site, string $slug, string $env = 'production'): string
    {
        return self::CACHE_PREFIX . "site:{$site->id}:section:{$slug}:env:{$env}";
    }

    /**
     * Pobiera dane z cache.
     *
     * @param string $key Klucz cache
     * @return Collection<int, \App\Modules\Content\Models\SiteContent>|null
     */
    public function get(string $key): ?Collection
    {
        $data = Cache::get($key);

        if ($data === null) {
            return null;
        }

        return $data instanceof Collection ? $data : null;
    }

    /**
     * Zapisuje dane w cache.
     *
     * @param string $key Klucz cache
     * @param Collection<int, \App\Modules\Content\Models\SiteContent> $data Dane
     * @param int $ttl TTL w sekundach
     * @return void
     */
    public function put(string $key, Collection $data, int $ttl = self::DEFAULT_TTL): void
    {
        Cache::put($key, $data, $ttl);
    }

    /**
     * Sprawdza czy klucz istnieje w cache.
     *
     * @param string $key Klucz cache
     * @return bool
     */
    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Usuwa konkretny klucz z cache.
     *
     * @param string $key Klucz cache
     * @return bool
     */
    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * Inwaliduje cache dla strony.
     *
     * @param Site|null $site Strona (null = wszystkie)
     * @param string|null $env Środowisko (null = oba)
     * @return void
     */
    public function invalidate(?Site $site = null, ?string $env = null): void
    {
        if ($site === null) {
            // Wyczyść cały cache kontentu (ostrożnie!)
            $this->flushContentCache();
            return;
        }

        $environments = $env !== null ? [$env] : ['staging', 'production'];

        foreach ($environments as $environment) {
            // Główny cache strony
            $this->forget($this->buildKey($site, $environment));

            // Cache sekcji - nie mamy listy slugów, więc używamy tagów lub wzorca
            // W przypadku Redis możemy użyć wildcard delete
            $this->invalidateSiteSections($site, $environment);
        }
    }

    /**
     * Inwaliduje cache po publikacji.
     *
     * @param Site $site Strona
     * @param string $env Środowisko
     * @param array<string> $slugs Slugi opublikowanych sekcji
     * @return void
     */
    public function invalidateOnPublish(Site $site, string $env, array $slugs = []): void
    {
        // Główny cache
        $this->forget($this->buildKey($site, $env));

        // Cache poszczególnych sekcji
        foreach ($slugs as $slug) {
            $this->forget($this->buildSectionKey($site, $slug, $env));
        }
    }

    /**
     * Inwaliduje wszystkie sekcje dla strony.
     *
     * @param Site $site Strona
     * @param string $env Środowisko
     * @return void
     */
    private function invalidateSiteSections(Site $site, string $env): void
    {
        // Redis: możemy użyć wildcard pattern
        $cacheStore = Cache::getStore();

        // Dla Redis
        if ($cacheStore instanceof \Illuminate\Cache\RedisStore) {
            /** @var \Illuminate\Redis\Connections\Connection $redis */
            $redis = $cacheStore->connection();
            $pattern = config('cache.prefix') . self::CACHE_PREFIX . "site:{$site->id}:*:env:{$env}";
            
            // Znajdź i usuń pasujące klucze
            $keys = $redis->keys($pattern);
            if (! empty($keys)) {
                $redis->del(...$keys);
            }
            return;
        }

        // Dla file cache - usuń główny klucz, sekcje będą stale po TTL
        // W praktyce powinniśmy przechowywać listę slugów
        $this->forget($this->buildKey($site, $env));
    }

    /**
     * Czyści cały cache kontentu.
     *
     * UWAGA: Używać ostrożnie w produkcji!
     *
     * @return void
     */
    private function flushContentCache(): void
    {
        $cacheStore = Cache::getStore();

        if ($cacheStore instanceof \Illuminate\Cache\RedisStore) {
            /** @var \Illuminate\Redis\Connections\Connection $redis */
            $redis = $cacheStore->connection();
            $pattern = config('cache.prefix') . self::CACHE_PREFIX . '*';
            
            $keys = $redis->keys($pattern);
            if (! empty($keys)) {
                $redis->del(...$keys);
            }
            return;
        }

        // Dla innych store - nie możemy selektywnie czyścić
        // Log warning
        logger()->warning('ContentCacheService: Cannot selectively flush content cache for non-Redis store');
    }

    /**
     * Zwraca statystyki cache dla strony.
     *
     * @param Site $site Strona
     * @return array{staging: bool, production: bool}
     */
    public function getCacheStatus(Site $site): array
    {
        return [
            'staging' => $this->has($this->buildKey($site, 'staging')),
            'production' => $this->has($this->buildKey($site, 'production')),
        ];
    }

    /**
     * Wygrzewa cache dla strony (pre-cache).
     *
     * @param Site $site Strona
     * @param string $env Środowisko
     * @param Collection<int, \App\Modules\Content\Models\SiteContent> $contents Treści
     * @return void
     */
    public function warmCache(Site $site, string $env, Collection $contents): void
    {
        $this->put($this->buildKey($site, $env), $contents);
    }
}
