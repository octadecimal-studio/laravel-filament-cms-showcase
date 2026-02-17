<?php

declare(strict_types=1);

namespace App\Plugins\Core;

use App\Plugins\Core\Contracts\PluginInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider dla systemu pluginów.
 *
 * Odpowiada za:
 * - Auto-discovery pluginów w app/Plugins/
 * - Rejestrację pluginów w PluginRegistry
 * - Ładowanie routes pluginów
 * - Rejestrację Filament Resources/Pages/Widgets
 */
class PluginServiceProvider extends ServiceProvider
{
    /**
     * Lista pluginów do zarejestrowania.
     *
     * Auto-discovery lub ręczna lista.
     *
     * @var array<class-string<PluginInterface>>
     */
    protected array $plugins = [];

    /**
     * Rejestracja serwisów.
     */
    public function register(): void
    {
        // Singleton dla PluginRegistry
        $this->app->singleton(PluginRegistry::class, function () {
            return new PluginRegistry();
        });

        // Alias dla wygody
        $this->app->alias(PluginRegistry::class, 'plugins');

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../../../config/plugins.php',
            'plugins'
        );
    }

    /**
     * Bootstrap serwisów.
     */
    public function boot(): void
    {
        // Publikacja konfiguracji
        $this->publishes([
            __DIR__ . '/../../../config/plugins.php' => config_path('plugins.php'),
        ], 'plugin-config');

        // Auto-discovery pluginów
        $this->discoverPlugins();

        // Rejestracja pluginów
        $this->registerPlugins();

        // Ładowanie routes pluginów
        $this->loadPluginRoutes();
    }

    /**
     * Auto-discovery pluginów w katalogu app/Plugins/.
     *
     * Szuka klas *Plugin.php implementujących PluginInterface.
     */
    protected function discoverPlugins(): void
    {
        $pluginsPath = app_path('Plugins');

        if (!File::isDirectory($pluginsPath)) {
            return;
        }

        // Pobierz wszystkie katalogi pluginów (pomijając Core)
        $directories = File::directories($pluginsPath);

        foreach ($directories as $directory) {
            $pluginName = basename($directory);

            // Pomiń katalog Core
            if ($pluginName === 'Core') {
                continue;
            }

            // Szukaj pliku {PluginName}Plugin.php
            $pluginFile = $directory . '/' . $pluginName . 'Plugin.php';

            if (!File::exists($pluginFile)) {
                continue;
            }

            // Zbuduj pełną nazwę klasy
            $className = "App\\Plugins\\{$pluginName}\\{$pluginName}Plugin";

            // Sprawdź czy klasa istnieje i implementuje interfejs
            if (class_exists($className) && is_subclass_of($className, PluginInterface::class)) {
                $this->plugins[] = $className;
            }
        }
    }

    /**
     * Rejestracja pluginów w PluginRegistry.
     */
    protected function registerPlugins(): void
    {
        $registry = $this->app->make(PluginRegistry::class);

        // Dodaj pluginy z konfiguracji (ręczne)
        $configPlugins = config('plugins.plugins', []);
        $allPlugins = array_unique(array_merge($this->plugins, $configPlugins));

        foreach ($allPlugins as $pluginClass) {
            if (!class_exists($pluginClass)) {
                continue;
            }

            try {
                $plugin = $this->app->make($pluginClass);
                $registry->register($plugin);
            } catch (\Throwable $e) {
                // Loguj błąd ale nie przerywaj bootstrap
                report($e);
            }
        }
    }

    /**
     * Ładowanie routes wszystkich aktywnych pluginów.
     */
    protected function loadPluginRoutes(): void
    {
        // Routes ładujemy tylko gdy nie jesteśmy w konsoli
        // lub gdy jawnie tego chcemy (np. dla testów)
        if ($this->app->runningInConsole() && !config('plugins.load_routes_in_console', false)) {
            return;
        }

        $registry = $this->app->make(PluginRegistry::class);
        $registry->registerAllRoutes();
    }
}
