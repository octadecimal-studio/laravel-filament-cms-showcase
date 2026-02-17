<?php

declare(strict_types=1);

namespace App\Modules\Plugins\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

/**
 * Serwis do zarządzania pluginami Filament.
 */
class PluginService
{
    /**
     * Instaluje plugin przez Composer.
     */
    public function install(string $package, ?string $version = null): void
    {
        $command = "composer require {$package}";
        if ($version) {
            $command .= ":{$version}";
        }

        $result = Process::path(base_path())
            ->timeout(300)
            ->run($command);

        if (!$result->successful()) {
            throw new \RuntimeException("Błąd instalacji: " . $result->errorOutput());
        }

        // Wywołaj package:discover aby zarejestrować service providers
        Artisan::call('package:discover');
    }

    /**
     * Odinstalowuje plugin przez Composer.
     */
    public function uninstall(string $package): void
    {
        $result = Process::path(base_path())
            ->timeout(300)
            ->run("composer remove {$package}");

        if (!$result->successful()) {
            throw new \RuntimeException("Błąd odinstalowania: " . $result->errorOutput());
        }

        Artisan::call('package:discover');
    }

    /**
     * Wyszukuje pluginy w Packagist API.
     *
     * @param string $query Fraza wyszukiwania (opcjonalna)
     * @param array<string> $categories Lista kategorii do filtrowania (opcjonalna)
     * @param int $limit Maksymalna liczba wyników
     * @return array<int, array<string, mixed>>
     */
    public function searchPackagist(string $query = '', array $categories = [], int $limit = 20): array
    {
        // Buduj query dla Packagist
        $searchQuery = '';
        if (!empty($query)) {
            // Jeśli query nie zawiera "filament", dodaj to automatycznie
            if (!str_contains(strtolower($query), 'filament')) {
                $searchQuery = $query . ' filament';
            } else {
                $searchQuery = $query;
            }
        } else {
            // Jeśli tylko kategorie, szukaj ogólnie po filament
            $searchQuery = 'filament';
        }
        
        $url = "https://packagist.org/search.json?q=" . urlencode($searchQuery) . "&per_page=" . min($limit * 2, 100); // Pobierz więcej aby móc filtrować
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            Log::warning("Błąd wyszukiwania w Packagist: HTTP {$httpCode}");
            return [];
        }

        $data = json_decode($response, true);
        $results = $data['results'] ?? [];
        
        // Filtruj tylko pakiety związane z Filament
        $filtered = array_filter($results, function ($result) {
            $name = strtolower($result['name'] ?? '');
            $description = strtolower($result['description'] ?? '');
            return str_contains($name, 'filament') || 
                   str_contains($description, 'filament') ||
                   str_contains($name, 'filament-plugin');
        });
        
        // Jeśli wybrano kategorie, filtruj po nich
        if (!empty($categories)) {
            $filtered = array_filter($filtered, function ($result) use ($categories) {
                $pluginCategory = $this->detectCategory($result);
                return in_array($pluginCategory, $categories);
            });
        }
        
        // Jeśli jest query, dodatkowo filtruj po słowie kluczowym
        if (!empty($query)) {
            $queryLower = strtolower($query);
            $filtered = array_filter($filtered, function ($result) use ($queryLower) {
                $name = strtolower($result['name'] ?? '');
                $description = strtolower($result['description'] ?? '');
                $keywords = array_map('strtolower', $result['keywords'] ?? []);
                
                return str_contains($name, $queryLower) || 
                       str_contains($description, $queryLower) ||
                       !empty(array_filter($keywords, fn($kw) => str_contains($kw, $queryLower)));
            });
        }
        
        // Ogranicz do limitu
        return array_slice(array_values($filtered), 0, $limit);
    }

    /**
     * Wykrywa kategorię pluginu na podstawie jego danych.
     *
     * @param array<string, mixed> $result Wynik z Packagist
     * @return string Kategoria pluginu
     */
    private function detectCategory(array $result): string
    {
        $name = strtolower($result['name'] ?? '');
        $description = strtolower($result['description'] ?? '');
        $keywords = array_map('strtolower', $result['keywords'] ?? []);
        
        $allText = $name . ' ' . $description . ' ' . implode(' ', $keywords);
        
        // Security
        if (preg_match('/\b(shield|permission|role|auth|security|rbac|access|guard)\b/', $allText)) {
            return 'security';
        }
        
        // UI
        if (preg_match('/\b(palette|theme|color|ui|widget|layout|sidebar|menu|navigation|design)\b/', $allText)) {
            return 'ui';
        }
        
        // Content
        if (preg_match('/\b(media|file|upload|image|video|editor|content|rich|text|wysiwyg)\b/', $allText)) {
            return 'content';
        }
        
        // Integration
        if (preg_match('/\b(api|integration|webhook|service|third.?party|external|connect|sync)\b/', $allText)) {
            return 'integration';
        }
        
        // Developer
        if (preg_match('/\b(debug|test|tool|dev|development|helper|utility|command|console)\b/', $allText)) {
            return 'developer';
        }
        
        return 'other';
    }

    /**
     * Pobiera szczegóły pakietu z Packagist.
     */
    public function getPackageDetails(string $package): ?array
    {
        $url = "https://packagist.org/packages/{$package}.json";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['package'] ?? null;
    }

    /**
     * Próbuje automatycznie wykryć klasę pluginu Filament dla zainstalowanego pakietu.
     */
    public function detectPluginClass(string $package): ?string
    {
        // Sprawdź czy pakiet jest zainstalowany
        $composerLockPath = base_path('composer.lock');
        if (!file_exists($composerLockPath)) {
            return null;
        }

        $composerLock = json_decode(file_get_contents($composerLockPath), true);
        $packages = $composerLock['packages'] ?? [];
        
        foreach ($packages as $pkg) {
            if (($pkg['name'] ?? '') === $package) {
                // Sprawdź autoload namespace
                $autoload = $pkg['autoload']['psr-4'] ?? [];
                if (empty($autoload)) {
                    continue;
                }

                // Pobierz pierwszy namespace
                $namespace = array_key_first($autoload);
                $namespace = rtrim($namespace, '\\');

                // Próbuj znaleźć klasę Plugin w różnych wariantach
                $possibleClasses = [
                    $namespace . '\\Plugin',
                    $namespace . '\\FilamentPlugin',
                    $namespace . '\\WebTerminalPlugin', // Dla web-terminal
                ];
                
                // Sprawdź też czy ServiceProvider rejestruje plugin
                $serviceProviderClass = $namespace . '\\ServiceProvider';
                if (class_exists($serviceProviderClass, false)) {
                    try {
                        $reflection = new \ReflectionClass($serviceProviderClass);
                        $file = $reflection->getFileName();
                        $content = file_get_contents($file);
                        
                        // Szukaj wzorców typu "WebTerminalPlugin::make()" lub "new WebTerminalPlugin"
                        if (preg_match('/(\w+Plugin)::make|new\s+(\w+Plugin)/', $content, $matches)) {
                            $pluginClassName = $matches[1] ?? $matches[2];
                            $possibleClasses[] = $namespace . '\\' . $pluginClassName;
                        }
                    } catch (\Throwable $e) {
                        // Ignoruj błędy
                    }
                }

                // Sprawdź też w vendor
                $vendorPath = base_path("vendor/{$package}");
                if (is_dir($vendorPath)) {
                    // Szukaj plików *Plugin.php
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($vendorPath)
                    );
                    
                    foreach ($iterator as $file) {
                        if ($file->isFile() && $file->getExtension() === 'php') {
                            $content = file_get_contents($file->getPathname());
                            
                            // Szukaj klasy która implementuje Plugin lub extends Plugin
                            if (preg_match('/class\s+(\w+Plugin)\s+(?:extends|implements)\s+.*/', $content, $matches)) {
                                // Znajdź namespace w pliku
                                if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatches)) {
                                    $fullClass = $nsMatches[1] . '\\' . $matches[1];
                                    // Sprawdź czy klasa istnieje (bez autoloadera)
                                    if (class_exists($fullClass, false)) {
                                        return $fullClass;
                                    }
                                }
                            }
                            
                            // Alternatywnie: szukaj po nazwie pliku
                            $fileName = $file->getFilename();
                            if (str_ends_with($fileName, 'Plugin.php')) {
                                if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatches)) {
                                    $className = str_replace('.php', '', $fileName);
                                    $fullClass = $nsMatches[1] . '\\' . $className;
                                    if (class_exists($fullClass, false)) {
                                        return $fullClass;
                                    }
                                }
                            }
                        }
                    }
                }

                // Sprawdź czy któraś z możliwych klas istnieje (bez autoloadera)
                foreach ($possibleClasses as $class) {
                    if (class_exists($class, false)) {
                        // Sprawdź czy implementuje odpowiedni interfejs (bez autoloadera)
                        try {
                            if (method_exists($class, 'make') || @is_subclass_of($class, \Filament\Panel\Concerns\HasPlugins::class)) {
                                return $class;
                            }
                        } catch (\Throwable $e) {
                            // Ignoruj błędy sprawdzania
                            continue;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Sprawdza kompatybilność pluginu z obecną wersją Filament.
     */
    public function checkCompatibility(string $package, ?string $class = null): array
    {
        $filamentVersion = \Composer\InstalledVersions::getVersion('filament/filament') ?? '3.0.0';
        $majorVersion = (int) explode('.', $filamentVersion)[0];
        
        // Sprawdź czy plugin wymaga filament/schemas (tylko v4+)
        // Nie używamy class_exists() bo to może wywołać autoload i fatal error
        if ($class) {
            // Sprawdź bezpośrednio w plikach vendor
            $vendorPath = base_path("vendor/{$package}");
            if (is_dir($vendorPath)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($vendorPath)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());
                        
                        // Sprawdź czy plik używa Filament Schemas
                        if (str_contains($content, 'Filament\\Schemas') || 
                            str_contains($content, 'InteractsWithSchemas') ||
                            str_contains($content, 'use Filament\\Schemas')) {
                            
                            if ($majorVersion < 4) {
                                return [
                                    'compatible' => false,
                                    'message' => "Plugin {$package} wymaga Filament v4+ (filament/schemas), ale używasz Filament v{$majorVersion}. Zaktualizuj Filament lub użyj innego pluginu.",
                                ];
                            }
                        }
                    }
                }
            }
            
            // Sprawdź też w composer.json pluginu
            $composerJsonPath = base_path("vendor/{$package}/composer.json");
            if (file_exists($composerJsonPath)) {
                $composerJson = json_decode(file_get_contents($composerJsonPath), true);
                $requires = array_merge(
                    $composerJson['require'] ?? [],
                    $composerJson['require-dev'] ?? []
                );
                
                if (isset($requires['filament/schemas'])) {
                    if ($majorVersion < 4) {
                        return [
                            'compatible' => false,
                            'message' => "Plugin {$package} wymaga filament/schemas (Filament v4+), ale używasz Filament v{$majorVersion}.",
                        ];
                    }
                }
            }
        }
        
        return [
            'compatible' => true,
            'message' => 'Plugin jest kompatybilny z obecną wersją Filament.',
        ];
    }

    /**
     * Synchronizuje zainstalowane pluginy z bazą danych.
     */
    public function syncInstalledPlugins(): void
    {
        $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);
        $installedPackages = $composerJson['require'] ?? [];

        // Filtruj tylko pakiety Filament
        $filamentPackages = array_filter($installedPackages, function ($package) {
            return str_contains($package, 'filament') || str_contains($package, 'filament-');
        }, ARRAY_FILTER_USE_KEY);

        foreach ($filamentPackages as $package => $version) {
            \App\Models\Plugin::updateOrCreate(
                ['package' => $package],
                [
                    'is_installed' => true,
                    'version' => $version,
                ]
            );
        }
    }
}
