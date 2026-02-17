<?php

declare(strict_types=1);

namespace App\Plugins\Core;

use App\Models\Site;
use App\Plugins\Core\Contracts\PluginInterface;
use App\Plugins\Core\Models\PluginInstallation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Rejestr wszystkich dostępnych pluginów.
 *
 * Przechowuje informacje o załadowanych pluginach i ich instalacjach.
 * Singleton zarządzany przez PluginServiceProvider.
 */
class PluginRegistry
{
    /**
     * Załadowane pluginy (slug => instance).
     *
     * @var array<string, PluginInterface>
     */
    protected array $plugins = [];

    /**
     * Rejestracja pluginu w rejestrze.
     *
     * @param PluginInterface $plugin
     * @return self
     */
    public function register(PluginInterface $plugin): self
    {
        $slug = $plugin::slug();

        if (isset($this->plugins[$slug])) {
            Log::warning("Plugin '{$slug}' jest już zarejestrowany, nadpisuję.");
        }

        $this->plugins[$slug] = $plugin;

        Log::debug("Plugin '{$slug}' v{$plugin::version()} zarejestrowany.");

        return $this;
    }

    /**
     * Pobranie pluginu po slug.
     *
     * @param string $slug
     * @return PluginInterface|null
     */
    public function get(string $slug): ?PluginInterface
    {
        return $this->plugins[$slug] ?? null;
    }

    /**
     * Sprawdzenie czy plugin jest zarejestrowany.
     *
     * @param string $slug
     * @return bool
     */
    public function has(string $slug): bool
    {
        return isset($this->plugins[$slug]);
    }

    /**
     * Pobranie wszystkich zarejestrowanych pluginów.
     *
     * @return Collection<string, PluginInterface>
     */
    public function all(): Collection
    {
        return collect($this->plugins);
    }

    /**
     * Pobranie tylko aktywnych (enabled) pluginów.
     *
     * @return Collection<string, PluginInterface>
     */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn (PluginInterface $p) => $p::isEnabled());
    }

    /**
     * Pobranie pluginów zainstalowanych na stronie.
     *
     * @param Site $site
     * @return Collection<string, PluginInterface>
     */
    public function installedOn(Site $site): Collection
    {
        $installedSlugs = PluginInstallation::where('site_id', $site->id)
            ->where('status', 'active')
            ->pluck('plugin_slug')
            ->toArray();

        return $this->all()->filter(
            fn (PluginInterface $p, string $slug) => in_array($slug, $installedSlugs)
        );
    }

    /**
     * Pobranie pluginów dostępnych do instalacji na stronie.
     *
     * Zwraca pluginy, które są enabled ale nie są jeszcze zainstalowane.
     *
     * @param Site $site
     * @return Collection<string, PluginInterface>
     */
    public function availableFor(Site $site): Collection
    {
        $installed = $this->installedOn($site)->keys()->toArray();

        return $this->enabled()->filter(
            fn (PluginInterface $p, string $slug) => !in_array($slug, $installed)
        );
    }

    /**
     * Instalacja pluginu na stronie.
     *
     * @param string $slug
     * @param Site $site
     * @param array $config
     * @return PluginInstallation
     * @throws \Exception
     */
    public function install(string $slug, Site $site, array $config = []): PluginInstallation
    {
        $plugin = $this->get($slug);

        if (!$plugin) {
            throw new \InvalidArgumentException("Plugin '{$slug}' nie istnieje.");
        }

        if (!$plugin::isEnabled()) {
            throw new \RuntimeException("Plugin '{$slug}' jest wyłączony.");
        }

        if ($plugin->isInstalled($site)) {
            throw new \RuntimeException("Plugin '{$slug}' jest już zainstalowany na tej stronie.");
        }

        // Uruchom instalację w pluginie
        $plugin->install($site, $config);

        // Pobierz utworzoną instalację
        return PluginInstallation::where('site_id', $site->id)
            ->where('plugin_slug', $slug)
            ->firstOrFail();
    }

    /**
     * Deinstalacja pluginu ze strony.
     *
     * @param string $slug
     * @param Site $site
     * @return void
     */
    public function uninstall(string $slug, Site $site): void
    {
        $plugin = $this->get($slug);

        if (!$plugin) {
            throw new \InvalidArgumentException("Plugin '{$slug}' nie istnieje.");
        }

        $plugin->uninstall($site);
    }

    /**
     * Pobranie wszystkich Filament Resources z zainstalowanych pluginów.
     *
     * @param Site|null $site Opcjonalna strona (jeśli null, zwraca z wszystkich pluginów)
     * @return array<class-string>
     */
    public function filamentResources(?Site $site = null): array
    {
        $plugins = $site ? $this->installedOn($site) : $this->enabled();

        return $plugins
            ->flatMap(fn (PluginInterface $p) => $p->filamentResources())
            ->toArray();
    }

    /**
     * Pobranie wszystkich Filament Pages z zainstalowanych pluginów.
     *
     * @param Site|null $site
     * @return array<class-string>
     */
    public function filamentPages(?Site $site = null): array
    {
        $plugins = $site ? $this->installedOn($site) : $this->enabled();

        return $plugins
            ->flatMap(fn (PluginInterface $p) => $p->filamentPages())
            ->toArray();
    }

    /**
     * Pobranie wszystkich Filament Widgets z zainstalowanych pluginów.
     *
     * @param Site|null $site
     * @return array<class-string>
     */
    public function filamentWidgets(?Site $site = null): array
    {
        $plugins = $site ? $this->installedOn($site) : $this->enabled();

        return $plugins
            ->flatMap(fn (PluginInterface $p) => $p->filamentWidgets())
            ->toArray();
    }

    /**
     * Rejestracja routes wszystkich aktywnych pluginów.
     *
     * @return void
     */
    public function registerAllRoutes(): void
    {
        foreach ($this->enabled() as $plugin) {
            $plugin->registerRoutes();
        }
    }
}
