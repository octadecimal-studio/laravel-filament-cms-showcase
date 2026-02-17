<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use App\Modules\Content\Models\ContentTemplate;
use App\Modules\Generator\Models\GeneratedTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Serwis do generowania komponentów Next.js z wygenerowanych szablonów AI.
 */
final class ComponentGeneratorService
{
    /**
     * Generuj komponent Next.js z wygenerowanego szablonu.
     *
     * @param  GeneratedTemplate  $generatedTemplate  Wygenerowany szablon AI
     * @param  array<string, mixed>  $variables  Zmienne (kolory, fonty, teksty)
     */
    public function generateComponent(
        GeneratedTemplate $generatedTemplate,
        array $variables = []
    ): ContentTemplate {
        $generatedCode = $generatedTemplate->generated_code ?? [];

        // Waliduj kod
        $validatedCode = $this->validateCode($generatedCode);

        // Zastosuj zmienne
        $appliedCode = $this->applyVariables($validatedCode, $variables);

        // Wygeneruj strukturę dla ContentTemplate
        $structure = $this->buildStructure($appliedCode);

        // Utwórz ContentTemplate
        $contentTemplate = ContentTemplate::create([
            'name' => $this->extractName($generatedCode) ?? 'Wygenerowany szablon',
            'slug' => Str::slug($this->extractName($generatedCode) ?? 'wygenerowany-szablon-'.now()->timestamp),
            'category' => $this->extractCategory($generatedCode) ?? 'section',
            'description' => $this->extractDescription($generatedCode),
            'structure' => $structure,
            'default_data' => $this->extractDefaultData($appliedCode),
            'config' => $this->buildConfig($variables),
            'tags' => $this->extractTags($generatedCode),
            'is_active' => true,
        ]);

        Log::info('Component generated and saved to ContentTemplate', [
            'content_template_id' => $contentTemplate->id,
            'generated_template_id' => $generatedTemplate->id,
        ]);

        return $contentTemplate;
    }

    /**
     * Waliduj wygenerowany kod.
     *
     * @param  array<string, mixed>  $code  Wygenerowany kod
     * @return array<string, mixed>
     */
    private function validateCode(array $code): array
    {
        // Podstawowa walidacja struktury
        if (! isset($code['components']) || ! is_array($code['components'])) {
            throw new \InvalidArgumentException('Invalid code structure: missing components array');
        }

        $validated = [
            'components' => [],
            'metadata' => $code['metadata'] ?? [],
        ];

        foreach ($code['components'] as $component) {
            if (! isset($component['name']) || ! isset($component['code'])) {
                continue; // Pomiń nieprawidłowe komponenty
            }

            // Waliduj TypeScript/React syntax (podstawowa)
            $validatedCode = $this->validateTypeScriptSyntax($component['code'] ?? '');

            $validated['components'][] = [
                'name' => $component['name'],
                'type' => $component['type'] ?? 'tsx',
                'code' => $validatedCode,
                'dependencies' => $component['dependencies'] ?? [],
                'styles' => $component['styles'] ?? null,
            ];
        }

        return $validated;
    }

    /**
     * Waliduj składnię TypeScript (podstawowa walidacja).
     *
     * @param  string  $code  Kod TypeScript
     */
    private function validateTypeScriptSyntax(string $code): string
    {
        // Podstawowa walidacja - sprawdź czy kod zawiera podstawowe elementy React
        if (! str_contains($code, 'export') && ! str_contains($code, 'function')) {
            throw new \InvalidArgumentException('Invalid TypeScript code: missing export or function');
        }

        // Usuń potencjalnie niebezpieczne elementy
        $code = preg_replace('/eval\s*\(/', '/* eval removed */', $code);
        $code = preg_replace('/dangerouslySetInnerHTML/', '/* dangerouslySetInnerHTML removed */', $code);

        return $code;
    }

    /**
     * Zastosuj zmienne do kodu.
     *
     * @param  array<string, mixed>  $code  Kod
     * @param  array<string, mixed>  $variables  Zmienne
     * @return array<string, mixed>
     */
    private function applyVariables(array $code, array $variables): array
    {
        if (empty($variables)) {
            return $code;
        }

        $applied = $code;

        foreach ($applied['components'] as &$component) {
            $componentCode = $component['code'] ?? '';

            // Zastosuj kolory
            if (isset($variables['colors'])) {
                $componentCode = $this->applyColors($componentCode, $variables['colors']);
            }

            // Zastosuj fonty
            if (isset($variables['fonts'])) {
                $componentCode = $this->applyFonts($componentCode, $variables['fonts']);
            }

            // Zastosuj teksty
            if (isset($variables['texts'])) {
                $componentCode = $this->applyTexts($componentCode, $variables['texts']);
            }

            $component['code'] = $componentCode;
        }

        return $applied;
    }

    /**
     * Zastosuj kolory do kodu.
     *
     * @param  string  $code  Kod
     * @param  array<string, string>  $colors  Kolory
     */
    private function applyColors(string $code, array $colors): string
    {
        foreach ($colors as $key => $value) {
            // Zastąp placeholdery kolorów
            $code = str_replace("{{color.{$key}}}", $value, $code);
            $code = str_replace("{{colors.{$key}}}", $value, $code);
        }

        return $code;
    }

    /**
     * Zastosuj fonty do kodu.
     *
     * @param  string  $code  Kod
     * @param  array<string, string>  $fonts  Fonty
     */
    private function applyFonts(string $code, array $fonts): string
    {
        foreach ($fonts as $key => $value) {
            // Zastąp placeholdery fontów
            $code = str_replace("{{font.{$key}}}", $value, $code);
            $code = str_replace("{{fonts.{$key}}}", $value, $code);
        }

        return $code;
    }

    /**
     * Zastosuj teksty do kodu.
     *
     * @param  string  $code  Kod
     * @param  array<string, string>  $texts  Teksty
     */
    private function applyTexts(string $code, array $texts): string
    {
        foreach ($texts as $key => $value) {
            // Zastąp placeholdery tekstów
            $code = str_replace("{{text.{$key}}}", $value, $code);
            $code = str_replace("{{texts.{$key}}}", $value, $code);
        }

        return $code;
    }

    /**
     * Buduj strukturę dla ContentTemplate.
     *
     * @param  array<string, mixed>  $code  Kod
     * @return array<string, mixed>
     */
    private function buildStructure(array $code): array
    {
        $structure = [
            'type' => 'generated',
            'components' => [],
        ];

        foreach ($code['components'] ?? [] as $component) {
            $structure['components'][] = [
                'name' => $component['name'],
                'type' => $component['type'] ?? 'tsx',
                'code' => $component['code'],
                'dependencies' => $component['dependencies'] ?? [],
            ];
        }

        return $structure;
    }

    /**
     * Buduj konfigurację z zmiennych.
     *
     * @param  array<string, mixed>  $variables  Zmienne
     * @return array<string, mixed>
     */
    private function buildConfig(array $variables): array
    {
        return [
            'colors' => $variables['colors'] ?? [],
            'fonts' => $variables['fonts'] ?? [],
            'spacing' => $variables['spacing'] ?? [],
        ];
    }

    /**
     * Wyciągnij nazwę z kodu.
     *
     * @param  array<string, mixed>  $code  Kod
     */
    private function extractName(array $code): ?string
    {
        return $code['metadata']['name'] ?? $code['metadata']['description'] ?? null;
    }

    /**
     * Wyciągnij kategorię z kodu.
     *
     * @param  array<string, mixed>  $code  Kod
     */
    private function extractCategory(array $code): ?string
    {
        return $code['metadata']['category'] ?? null;
    }

    /**
     * Wyciągnij opis z kodu.
     *
     * @param  array<string, mixed>  $code  Kod
     */
    private function extractDescription(array $code): ?string
    {
        return $code['metadata']['description'] ?? null;
    }

    /**
     * Wyciągnij domyślne dane z kodu.
     *
     * @param  array<string, mixed>  $code  Kod
     * @return array<string, mixed>
     */
    private function extractDefaultData(array $code): array
    {
        return $code['metadata']['default_data'] ?? [];
    }

    /**
     * Wyciągnij tagi z kodu.
     *
     * @param  array<string, mixed>  $code  Kod
     * @return array<string>
     */
    private function extractTags(array $code): array
    {
        return $code['metadata']['tags'] ?? [];
    }
}
