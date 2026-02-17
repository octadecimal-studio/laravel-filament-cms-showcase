<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Service do parsowania struktur szablonów Next.js.
 *
 * Analizuje katalog szablonu i ekstraktuje:
 * - Komponenty (z src/components/)
 * - Style (CSS, Tailwind config)
 * - Zależności (package.json)
 * - Strukturę plików
 */
final class TemplateParserService
{
    /**
     * Parsuj strukturę szablonu z katalogu.
     *
     * @param  string  $templatePath  Ścieżka do katalogu szablonu (względem templates/)
     * @return array<string, mixed>
     */
    public function parse(string $templatePath): array
    {
        $fullPath = base_path("templates/{$templatePath}");

        if (! File::exists($fullPath)) {
            throw new \InvalidArgumentException("Template path does not exist: {$templatePath}");
        }

        return [
            'components' => $this->parseComponents($fullPath),
            'dependencies' => $this->parseDependencies($fullPath),
            'styles' => $this->parseStyles($fullPath),
            'structure' => $this->parseStructure($fullPath),
            'config' => $this->parseConfig($fullPath),
        ];
    }

    /**
     * Parsuj komponenty z src/components/.
     *
     * @return array<string, array{name: string, path: string, type: string}>
     */
    private function parseComponents(string $templatePath): array
    {
        $componentsPath = "{$templatePath}/src/components";
        $components = [];

        if (! File::exists($componentsPath)) {
            return $components;
        }

        $files = File::allFiles($componentsPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'tsx' && $file->getExtension() !== 'ts') {
                continue;
            }

            $relativePath = str_replace("{$templatePath}/", '', $file->getPathname());
            $name = $file->getFilenameWithoutExtension();
            $directory = str_replace("{$componentsPath}/", '', $file->getPath());

            $components[] = [
                'name' => $name,
                'path' => $relativePath,
                'type' => $this->detectComponentType($name, $directory),
                'directory' => $directory !== $componentsPath ? $directory : null,
            ];
        }

        return $components;
    }

    /**
     * Wykryj typ komponentu na podstawie nazwy i katalogu.
     */
    private function detectComponentType(string $name, string $directory): string
    {
        $lowerName = Str::lower($name);
        $lowerDir = Str::lower($directory);

        if (Str::contains($lowerDir, 'layout')) {
            return 'layout';
        }

        if (Str::contains($lowerDir, 'section')) {
            return 'section';
        }

        if (Str::contains($lowerName, 'hero')) {
            return 'hero';
        }

        if (Str::contains($lowerName, 'footer')) {
            return 'footer';
        }

        if (Str::contains($lowerName, 'header')) {
            return 'header';
        }

        return 'component';
    }

    /**
     * Parsuj zależności z package.json.
     *
     * @return array<string, mixed>
     */
    private function parseDependencies(string $templatePath): array
    {
        $packageJsonPath = "{$templatePath}/package.json";

        if (! File::exists($packageJsonPath)) {
            return [];
        }

        $content = File::get($packageJsonPath);
        $package = json_decode($content, true);

        if (! is_array($package)) {
            return [];
        }

        return [
            'dependencies' => $package['dependencies'] ?? [],
            'devDependencies' => $package['devDependencies'] ?? [],
            'scripts' => $package['scripts'] ?? [],
            'framework' => $this->detectFramework($package),
        ];
    }

    /**
     * Wykryj framework na podstawie package.json.
     *
     * @param  array<string, mixed>  $package
     */
    private function detectFramework(array $package): string
    {
        $deps = array_merge(
            $package['dependencies'] ?? [],
            $package['devDependencies'] ?? []
        );

        if (isset($deps['next'])) {
            return 'Next.js';
        }

        if (isset($deps['react'])) {
            return 'React';
        }

        return 'Unknown';
    }

    /**
     * Parsuj style (CSS, Tailwind config).
     *
     * @return array<string, mixed>
     */
    private function parseStyles(string $templatePath): array
    {
        $styles = [];

        // Tailwind config
        $tailwindConfig = "{$templatePath}/tailwind.config.ts";
        if (File::exists($tailwindConfig)) {
            $styles['tailwind'] = true;
        }

        $tailwindConfigJs = "{$templatePath}/tailwind.config.js";
        if (File::exists($tailwindConfigJs)) {
            $styles['tailwind'] = true;
        }

        // Global CSS
        $globalsCss = "{$templatePath}/src/app/globals.css";
        if (File::exists($globalsCss)) {
            $styles['global_css'] = true;
        }

        return $styles;
    }

    /**
     * Parsuj strukturę plików.
     *
     * @return array<string, mixed>
     */
    private function parseStructure(string $templatePath): array
    {
        $structure = [
            'has_app_directory' => File::exists("{$templatePath}/src/app"),
            'has_pages_directory' => File::exists("{$templatePath}/src/pages"),
            'has_public' => File::exists("{$templatePath}/public"),
            'has_config' => File::exists("{$templatePath}/next.config.mjs") || File::exists("{$templatePath}/next.config.js"),
        ];

        // Liczba komponentów
        $componentsPath = "{$templatePath}/src/components";
        if (File::exists($componentsPath)) {
            $structure['components_count'] = count(File::allFiles($componentsPath));
        }

        return $structure;
    }

    /**
     * Parsuj konfigurację (next.config, tsconfig).
     *
     * @return array<string, mixed>
     */
    private function parseConfig(string $templatePath): array
    {
        $config = [
            'typescript' => File::exists("{$templatePath}/tsconfig.json"),
            'has_readme' => File::exists("{$templatePath}/README.md"),
            'has_description' => File::exists("{$templatePath}/DESCRIPTION.md"),
        ];

        // Screenshot
        $screenshot = "{$templatePath}/screenshot.png";
        if (File::exists($screenshot)) {
            $config['screenshot'] = "templates/{$templatePath}/screenshot.png";
        }

        return $config;
    }
}
