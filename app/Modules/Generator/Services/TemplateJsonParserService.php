<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Serwis do parsowania plików JSON z metadanymi szablonu.
 *
 * Analizuje przesłany plik JSON i ekstraktuje dane do wypełnienia formularza Template.
 */
final class TemplateJsonParserService
{
    /**
     * Parsuj plik JSON i zwróć dane szablonu.
     *
     * @param  UploadedFile  $file  Przesłany plik JSON
     * @return array<string, mixed> Dane szablonu do wypełnienia formularza
     */
    public function parse(UploadedFile $file): array
    {
        // Waliduj typ pliku
        if ($file->getMimeType() !== 'application/json' && $file->getClientOriginalExtension() !== 'json') {
            throw new \InvalidArgumentException('Plik musi być w formacie JSON');
        }

        // Odczytaj zawartość pliku
        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            throw new \RuntimeException('Nie można odczytać pliku JSON');
        }

        // Parsuj JSON
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Nieprawidłowy format JSON: '.json_last_error_msg());
        }

        // Ekstraktuj dane szablonu
        return $this->extractTemplateData($data, $file);
    }

    /**
     * Ekstraktuj dane szablonu z parsowanego JSON.
     *
     * @param  array<string, mixed>  $jsonData  Parsowane dane JSON
     * @param  UploadedFile  $file  Przesłany plik
     * @return array<string, mixed>
     */
    private function extractTemplateData(array $jsonData, UploadedFile $file): array
    {
        $result = [
            'name' => null,
            'slug' => null,
            'directory_path' => null,
            'category' => null,
            'tech_stack' => null,
            'description' => null,
            'metadata' => null,
            'tags' => null,
            'thumbnail_url' => null,
            'preview_url' => null,
        ];

        // Nazwa szablonu
        $result['name'] = $jsonData['name'] ?? $jsonData['template_name'] ?? $jsonData['title'] ?? null;

        // Slug - wygeneruj z nazwy jeśli nie ma
        if (isset($jsonData['slug'])) {
            $result['slug'] = $jsonData['slug'];
        } elseif ($result['name']) {
            $result['slug'] = Str::slug($result['name']);
        }

        // Directory path - zapisz nazwę pliku bez rozszerzenia jako ścieżkę
        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $result['directory_path'] = $fileName;

        // Kategoria
        $result['category'] = $jsonData['category'] ?? $jsonData['type'] ?? null;
        if ($result['category'] && ! in_array($result['category'], ['portfolio', 'landing', 'corporate', 'blog', 'ecommerce', 'other'])) {
            // Mapuj niestandardowe kategorie
            $result['category'] = $this->mapCategory($result['category']);
        }

        // Tech stack
        if (isset($jsonData['tech_stack'])) {
            $result['tech_stack'] = is_array($jsonData['tech_stack']) 
                ? $jsonData['tech_stack'] 
                : explode(',', (string) $jsonData['tech_stack']);
        } elseif (isset($jsonData['metadata']['tech_stack'])) {
            $result['tech_stack'] = is_array($jsonData['metadata']['tech_stack'])
                ? $jsonData['metadata']['tech_stack']
                : explode(',', (string) $jsonData['metadata']['tech_stack']);
        } elseif (isset($jsonData['dependencies'])) {
            // Ekstraktuj tech stack z dependencies
            $result['tech_stack'] = $this->extractTechStackFromDependencies($jsonData['dependencies']);
        }

        // Opis
        $result['description'] = $jsonData['description'] ?? $jsonData['desc'] ?? null;

        // Metadata - zachowaj pełną strukturę jeśli istnieje
        if (isset($jsonData['metadata'])) {
            $result['metadata'] = $jsonData['metadata'];
        } elseif (isset($jsonData['components']) || isset($jsonData['dependencies']) || isset($jsonData['structure'])) {
            // Zbuduj metadata z dostępnych danych
            $result['metadata'] = [
                'components' => $jsonData['components'] ?? [],
                'dependencies' => $jsonData['dependencies'] ?? [],
                'structure' => $jsonData['structure'] ?? [],
                'styles' => $jsonData['styles'] ?? [],
            ];
        }

        // Tags
        if (isset($jsonData['tags'])) {
            $result['tags'] = is_array($jsonData['tags']) 
                ? $jsonData['tags'] 
                : explode(',', (string) $jsonData['tags']);
        }

        // Thumbnail URL
        $result['thumbnail_url'] = $jsonData['thumbnail_url'] ?? $jsonData['thumbnail'] ?? $jsonData['screenshot_url'] ?? null;

        // Preview URL
        $result['preview_url'] = $jsonData['preview_url'] ?? $jsonData['preview'] ?? $jsonData['demo_url'] ?? null;

        return array_filter($result, fn ($value) => $value !== null);
    }

    /**
     * Ekstraktuj tech stack z dependencies.
     *
     * @param  array<string, mixed>  $dependencies
     * @return array<string>
     */
    private function extractTechStackFromDependencies(array $dependencies): array
    {
        $stack = [];
        $deps = array_merge(
            $dependencies['dependencies'] ?? [],
            $dependencies['devDependencies'] ?? []
        );

        // Mapuj popularne zależności na tech stack
        $techMap = [
            'next' => 'Next.js',
            'react' => 'React',
            'typescript' => 'TypeScript',
            'tailwindcss' => 'Tailwind CSS',
            'framer-motion' => 'Framer Motion',
            'sass' => 'SASS',
            'less' => 'Less',
            'styled-components' => 'Styled Components',
        ];

        foreach ($techMap as $dep => $tech) {
            if (isset($deps[$dep])) {
                $stack[] = $tech;
            }
        }

        return $stack;
    }

    /**
     * Mapuj niestandardową kategorię na jedną z dostępnych.
     *
     * @param  string  $category
     * @return string
     */
    private function mapCategory(string $category): string
    {
        $categoryLower = Str::lower($category);
        
        $mapping = [
            'portfolio' => 'portfolio',
            'portfolios' => 'portfolio',
            'landing' => 'landing',
            'landing-page' => 'landing',
            'landingpage' => 'landing',
            'corporate' => 'corporate',
            'business' => 'corporate',
            'company' => 'corporate',
            'blog' => 'blog',
            'ecommerce' => 'ecommerce',
            'e-commerce' => 'ecommerce',
            'shop' => 'ecommerce',
            'store' => 'ecommerce',
        ];

        return $mapping[$categoryLower] ?? 'other';
    }
}
