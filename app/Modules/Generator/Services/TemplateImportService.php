<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use App\Modules\Generator\Models\Template;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Service do importu szablonów z katalogu templates/ do bazy danych.
 */
final class TemplateImportService
{
    public function __construct(
        private TemplateParserService $parser
    ) {}

    /**
     * Importuj szablon z katalogu do bazy danych.
     *
     * @param  string  $templatePath  Ścieżka względem templates/ (np. octadecimal.studio)
     * @param  string  $tenantId  UUID tenanta
     * @param  array<string, mixed>  $overrides  Nadpisania metadanych
     */
    public function import(string $templatePath, string $tenantId, array $overrides = []): Template
    {
        // Parsuj strukturę szablonu
        $parsed = $this->parser->parse($templatePath);

        // Wygeneruj slug z nazwy katalogu
        $slug = $overrides['slug'] ?? Str::slug(basename($templatePath));

        // Sprawdź czy szablon już istnieje
        $existing = Template::where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            // Aktualizuj istniejący
            return $this->updateTemplate($existing, $templatePath, $parsed, $overrides);
        }

        // Utwórz nowy
        return $this->createTemplate($templatePath, $tenantId, $slug, $parsed, $overrides);
    }

    /**
     * Utwórz nowy rekord Template.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $overrides
     */
    private function createTemplate(
        string $templatePath,
        string $tenantId,
        string $slug,
        array $parsed,
        array $overrides
    ): Template {
        $dependencies = $parsed['dependencies'] ?? [];
        $framework = $dependencies['framework'] ?? 'Next.js';

        // Ekstraktuj tech stack
        $techStack = $this->extractTechStack($dependencies);

        // Kategoria na podstawie nazwy
        $category = $overrides['category'] ?? $this->detectCategory($templatePath);

        // Nazwa z katalogu lub override
        $name = $overrides['name'] ?? Str::title(str_replace(['.', '-', '_'], ' ', basename($templatePath)));

        // Screenshot URL
        $screenshot = $parsed['config']['screenshot'] ?? null;

        return Template::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'directory_path' => $templatePath,
            'category' => $category,
            'tech_stack' => $techStack,
            'description' => $overrides['description'] ?? $this->readDescription($templatePath),
            'metadata' => [
                'components' => $parsed['components'] ?? [],
                'structure' => $parsed['structure'] ?? [],
                'styles' => $parsed['styles'] ?? [],
                'dependencies' => $dependencies,
            ],
            'thumbnail_url' => $screenshot,
            'tags' => $overrides['tags'] ?? $this->generateTags($templatePath, $category, $techStack),
            'is_active' => $overrides['is_active'] ?? true,
            'is_premium' => $overrides['is_premium'] ?? false,
        ]);
    }

    /**
     * Aktualizuj istniejący rekord Template.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $overrides
     */
    private function updateTemplate(
        Template $template,
        string $templatePath,
        array $parsed,
        array $overrides
    ): Template {
        $dependencies = $parsed['dependencies'] ?? [];
        $techStack = $this->extractTechStack($dependencies);

        $template->update([
            'directory_path' => $templatePath,
            'tech_stack' => $techStack,
            'metadata' => [
                'components' => $parsed['components'] ?? [],
                'structure' => $parsed['structure'] ?? [],
                'styles' => $parsed['styles'] ?? [],
                'dependencies' => $dependencies,
            ],
            'thumbnail_url' => $parsed['config']['screenshot'] ?? $template->thumbnail_url,
        ]);

        return $template;
    }

    /**
     * Ekstraktuj tech stack z dependencies.
     *
     * @param  array<string, mixed>  $dependencies
     * @return array<string>
     */
    private function extractTechStack(array $dependencies): array
    {
        $stack = [];
        $deps = array_merge(
            $dependencies['dependencies'] ?? [],
            $dependencies['devDependencies'] ?? []
        );

        if (isset($deps['next'])) {
            $stack[] = 'Next.js';
        }

        if (isset($deps['react'])) {
            $stack[] = 'React';
        }

        if (isset($deps['typescript'])) {
            $stack[] = 'TypeScript';
        }

        if (isset($deps['tailwindcss'])) {
            $stack[] = 'Tailwind CSS';
        }

        return $stack;
    }

    /**
     * Wykryj kategorię na podstawie nazwy katalogu.
     */
    private function detectCategory(string $templatePath): string
    {
        $name = Str::lower(basename($templatePath));

        if (Str::contains($name, 'portfolio')) {
            return 'portfolio';
        }

        if (Str::contains($name, 'landing')) {
            return 'landing';
        }

        if (Str::contains($name, 'corporate') || Str::contains($name, 'prestige')) {
            return 'corporate';
        }

        if (Str::contains($name, 'blog')) {
            return 'blog';
        }

        return 'other';
    }

    /**
     * Przeczytaj opis z DESCRIPTION.md lub README.md.
     */
    private function readDescription(string $templatePath): ?string
    {
        $fullPath = base_path("templates/{$templatePath}");

        $descriptionFile = "{$fullPath}/DESCRIPTION.md";
        if (File::exists($descriptionFile)) {
            return File::get($descriptionFile);
        }

        $readmeFile = "{$fullPath}/README.md";
        if (File::exists($readmeFile)) {
            $content = File::get($readmeFile);
            // Pobierz pierwszy akapit
            $lines = explode("\n", $content);
            $firstParagraph = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || Str::startsWith($line, '#')) {
                    if (! empty($firstParagraph)) {
                        break;
                    }

                    continue;
                }
                $firstParagraph[] = $line;
            }

            return ! empty($firstParagraph) ? implode(' ', $firstParagraph) : null;
        }

        return null;
    }

    /**
     * Wygeneruj tagi dla szablonu.
     *
     * @param  array<string>  $techStack
     * @return array<string>
     */
    private function generateTags(string $templatePath, string $category, array $techStack): array
    {
        $tags = [$category];
        $tags = array_merge($tags, $techStack);

        $name = Str::lower(basename($templatePath));
        if (Str::contains($name, 'studio')) {
            $tags[] = 'studio';
        }

        return array_unique($tags);
    }
}
