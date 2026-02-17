<?php

declare(strict_types=1);

namespace App\Plugins\Core;

use App\Models\Site;
use App\Plugins\Core\Contracts\PluginInterface;
use App\Plugins\Core\Models\PluginInstallation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Bazowa klasa dla pluginów.
 *
 * Implementuje wspólną logikę dla wszystkich pluginów:
 * - instalacja/deinstalacja
 * - migracje
 * - rejestracja routes
 *
 * Pluginy dziedziczące muszą zaimplementować tylko statyczne metody
 * i dostarczyć konfigurację.
 */
abstract class AbstractPlugin implements PluginInterface
{
    /**
     * {@inheritdoc}
     */
    public static function isEnabled(): bool
    {
        return config('plugins.enabled.' . static::slug(), true);
    }

    /**
     * {@inheritdoc}
     */
    public static function icon(): string
    {
        return 'heroicon-o-puzzle-piece';
    }

    /**
     * {@inheritdoc}
     */
    public function install(Site $site, array $config = []): void
    {
        // Sprawdź czy już zainstalowany
        if ($this->isInstalled($site)) {
            throw new \RuntimeException(
                "Plugin '" . static::slug() . "' jest już zainstalowany na tej stronie."
            );
        }

        // Uruchom migracje pluginu (jeśli nie były uruchomione)
        $this->migrate();

        // Utwórz wpis instalacji
        PluginInstallation::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'plugin_slug' => static::slug(),
            'version' => static::version(),
            'config' => array_merge($this->defaultConfig(), $config),
            'status' => PluginInstallation::STATUS_ACTIVE,
            'installed_at' => now(),
            'installed_by' => Auth::id(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Site $site): void
    {
        PluginInstallation::where('site_id', $site->id)
            ->where('plugin_slug', static::slug())
            ->delete();

        // Dane pluginu NIE są usuwane - tylko instalacja
        // Dane mogą być potrzebne do audytu/archiwizacji
    }

    /**
     * {@inheritdoc}
     */
    public function isInstalled(Site $site): bool
    {
        return PluginInstallation::where('site_id', $site->id)
            ->where('plugin_slug', static::slug())
            ->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function migrate(): void
    {
        $migrationsPath = $this->basePath() . '/database/migrations';

        if (!is_dir($migrationsPath)) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $this->relativeMigrationsPath(),
            '--force' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(int $steps = 0): void
    {
        $options = [
            '--path' => $this->relativeMigrationsPath(),
            '--force' => true,
        ];

        if ($steps > 0) {
            $options['--step'] = $steps;
        }

        Artisan::call('migrate:rollback', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function registerRoutes(): void
    {
        $routesFile = $this->basePath() . '/routes/api.php';

        if (!file_exists($routesFile)) {
            return;
        }

        Route::prefix('api/v1/sites/{site}/plugins/' . static::slug())
            ->middleware(['api'])
            ->group($routesFile);
    }

    /**
     * {@inheritdoc}
     */
    public function filamentResources(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function filamentPages(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function filamentWidgets(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfig(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function basePath(): string
    {
        $reflection = new \ReflectionClass(static::class);
        return dirname($reflection->getFileName());
    }

    /**
     * Ścieżka do migracji względem app/
     *
     * @return string
     */
    protected function relativeMigrationsPath(): string
    {
        return 'app/Plugins/' . class_basename(static::class, 'Plugin') . '/database/migrations';
    }

    /**
     * Pobranie instalacji pluginu dla strony.
     *
     * @param Site $site
     * @return PluginInstallation|null
     */
    public function getInstallation(Site $site): ?PluginInstallation
    {
        return PluginInstallation::where('site_id', $site->id)
            ->where('plugin_slug', static::slug())
            ->first();
    }

    /**
     * Pobranie konfiguracji pluginu dla strony.
     *
     * @param Site $site
     * @return array
     */
    public function getConfig(Site $site): array
    {
        $installation = $this->getInstallation($site);

        return $installation
            ? array_merge($this->defaultConfig(), $installation->config ?? [])
            : $this->defaultConfig();
    }
}
