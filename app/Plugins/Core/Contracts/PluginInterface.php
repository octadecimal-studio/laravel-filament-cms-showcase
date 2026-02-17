<?php

declare(strict_types=1);

namespace App\Plugins\Core\Contracts;

use App\Models\Site;

/**
 * Kontrakt dla pluginów biznesowych.
 *
 * Każdy plugin musi implementować ten interfejs, aby móc być:
 * - automatycznie wykryty przez PluginServiceProvider
 * - zainstalowany/odinstalowany dla konkretnej strony
 * - zarejestrowany w Filament i API
 *
 * Pluginy różnią się od zwykłego contentu - zawierają logikę biznesową,
 * własne modele, migracje i mogą przechowywać dane wrażliwe (RODO).
 */
interface PluginInterface
{
    /**
     * Unikalny identyfikator pluginu (slug).
     *
     * Używany w:
     * - nazwach tabel (plugin_{slug}_*)
     * - routach (/api/v1/sites/{site}/plugins/{slug})
     * - konfiguracji
     *
     * @return string np. 'reservations', 'shop'
     */
    public static function slug(): string;

    /**
     * Nazwa wyświetlana w UI.
     *
     * @return string np. 'Rezerwacje', 'Sklep'
     */
    public static function name(): string;

    /**
     * Opis funkcjonalności pluginu.
     *
     * @return string
     */
    public static function description(): string;

    /**
     * Wersja pluginu (semver).
     *
     * @return string np. '1.0.0'
     */
    public static function version(): string;

    /**
     * Czy plugin jest globalnie aktywny.
     *
     * Wyłączony plugin nie może być instalowany na żadnej stronie.
     *
     * @return bool
     */
    public static function isEnabled(): bool;

    /**
     * Ikona pluginu (Heroicon lub Blade icon).
     *
     * @return string np. 'heroicon-o-calendar', 'heroicon-o-shopping-cart'
     */
    public static function icon(): string;

    /**
     * Instalacja pluginu dla konkretnej strony.
     *
     * Tworzy wpis w plugin_installations, uruchamia migracje
     * i inicjalizuje domyślną konfigurację.
     *
     * @param Site $site Strona, na której instalujemy plugin
     * @param array $config Opcjonalna konfiguracja początkowa
     * @return void
     * @throws \Exception Gdy instalacja nie powiedzie się
     */
    public function install(Site $site, array $config = []): void;

    /**
     * Deinstalacja pluginu ze strony.
     *
     * Usuwa wpis z plugin_installations. NIE usuwa danych z tabel pluginu
     * (soft delete / archiwizacja).
     *
     * @param Site $site Strona, z której odinstalowujemy plugin
     * @return void
     */
    public function uninstall(Site $site): void;

    /**
     * Sprawdzenie czy plugin jest zainstalowany na stronie.
     *
     * @param Site $site
     * @return bool
     */
    public function isInstalled(Site $site): bool;

    /**
     * Uruchomienie migracji pluginu.
     *
     * Migracje powinny znajdować się w:
     * app/Plugins/{PluginName}/database/migrations/
     *
     * @return void
     */
    public function migrate(): void;

    /**
     * Rollback migracji pluginu.
     *
     * @param int $steps Liczba kroków do cofnięcia (0 = wszystkie)
     * @return void
     */
    public function rollback(int $steps = 0): void;

    /**
     * Rejestracja routes pluginu.
     *
     * Routes powinny być w: app/Plugins/{PluginName}/routes/
     *
     * @return void
     */
    public function registerRoutes(): void;

    /**
     * Lista klas Filament Resources do rejestracji.
     *
     * @return array<class-string> np. [ReservationResource::class]
     */
    public function filamentResources(): array;

    /**
     * Lista klas Filament Pages do rejestracji.
     *
     * @return array<class-string>
     */
    public function filamentPages(): array;

    /**
     * Lista klas Filament Widgets do rejestracji.
     *
     * @return array<class-string>
     */
    public function filamentWidgets(): array;

    /**
     * Domyślna konfiguracja pluginu.
     *
     * Może być nadpisana per-site w plugin_installations.config
     *
     * @return array
     */
    public function defaultConfig(): array;

    /**
     * Ścieżka do katalogu pluginu.
     *
     * @return string
     */
    public function basePath(): string;
}
