<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use App\Modules\Content\Models\ContentBlock;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Serwis do integracji z template-analyzer.
 *
 * Analizuje szablony HTML/CSS i generuje ContentBlocks.
 */
final class TemplateAnalyzerService
{
    /**
     * Analizuj szablon HTML i wygeneruj ContentBlocks.
     *
     * @param  string  $htmlUrl  URL lub ścieżka do pliku HTML/TSX
     * @param  string  $projectName  Nazwa projektu
     * @return array<string, mixed> Analiza z komponentami i blokami
     */
    public function analyzeTemplate(string $htmlUrl, string $projectName): array
    {
        try {
            // Pobierz zawartość pliku (HTML lub TSX)
            $content = $this->fetchContent($htmlUrl);

            // Pre-processing
            $processedContent = $this->preprocessContent($content, $htmlUrl);

            // Analizuj przez Claude API
            $analysis = $this->analyzeWithClaude($processedContent, $projectName, $htmlUrl);

            // Ekstrahuj komponenty i bloki
            $components = $this->extractComponents($analysis);
            $blocks = $this->extractBlocks($analysis);

            return [
                'sections' => $analysis['sections'] ?? [],
                'components' => $components,
                'blocks' => $blocks,
                'content_types' => $analysis['contentTypes'] ?? [],
                'summary' => $analysis['summary'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('Template analysis failed', [
                'url' => $htmlUrl,
                'project' => $projectName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Analizuj całą strukturę szablonu Next.js (komponenty, strony, dane).
     *
     * @param  string  $templatePath  Ścieżka względem templates/
     * @param  string  $projectName  Nazwa projektu
     * @return array<string, mixed> Pełna analiza struktury
     */
    /**
     * Analizuj strukturę szablonu.
     *
     * @param  string  $templatePath  Ścieżka do szablonu
     * @param  string  $projectName  Nazwa projektu
     * @param  callable(int): void|null  $progressCallback  Callback dla progress (0-100)
     * @return array<string, mixed> Analiza struktury
     */
    public function analyzeTemplateStructure(string $templatePath, string $projectName, ?callable $progressCallback = null): array
    {
        try {
            $fullPath = base_path("templates/{$templatePath}");
            
            if (! file_exists($fullPath)) {
                throw new \InvalidArgumentException("Template path does not exist: {$templatePath}");
            }

            // Zbierz wszystkie komponenty i strony (10%)
            if ($progressCallback) {
                $progressCallback(10);
            }
            $components = $this->collectComponents($fullPath);
            $pages = $this->collectPages($fullPath);
            $dataFiles = $this->collectDataFiles($fullPath);

            // Przygotuj kontekst dla AI (20%)
            if ($progressCallback) {
                $progressCallback(20);
            }
            $context = $this->buildStructureContext($fullPath, $components, $pages, $dataFiles);

            // Analizuj przez Claude API (30-60%)
            if ($progressCallback) {
                $progressCallback(30);
            }
            $analysis = $this->analyzeStructureWithClaude($context, $projectName);
            if ($progressCallback) {
                $progressCallback(60);
            }

            // Wygeneruj ContentBlocks (70%)
            if ($progressCallback) {
                $progressCallback(70);
            }
            $blocks = $this->extractBlocks($analysis);

            return [
                'sections' => $analysis['sections'] ?? [],
                'components' => $components,
                'pages' => $pages,
                'data_requirements' => $analysis['dataRequirements'] ?? [],
                'blocks' => $blocks,
                'content_types' => $analysis['contentTypes'] ?? [],
                'summary' => $analysis['summary'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('Template structure analysis failed', [
                'template' => $templatePath,
                'project' => $projectName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generuj ContentBlocks z analizy.
     *
     * @param  array<string, mixed>  $analysis  Analiza szablonu
     * @return array<ContentBlock>
     */
    public function generateContentBlocks(array $analysis): array
    {
        $blocks = [];

        foreach ($analysis['blocks'] ?? [] as $blockData) {
            $schema = $this->buildContentBlockSchema($blockData);

            $block = ContentBlock::create([
                'name' => $blockData['name'] ?? 'Unnamed Block',
                'slug' => Str::slug($blockData['name'] ?? 'unnamed-block-'.uniqid()),
                'category' => $blockData['category'] ?? 'general',
                'description' => $blockData['description'] ?? '',
                'schema' => $schema,
                'default_data' => $blockData['defaultData'] ?? [],
                'is_active' => true,
            ]);

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Pobierz zawartość z URL lub pliku (HTML/TSX).
     *
     * @param  string  $urlOrPath  URL lub ścieżka
     * @return string Zawartość pliku
     */
    private function fetchContent(string $urlOrPath): string
    {
        // Jeśli to URL
        if (filter_var($urlOrPath, FILTER_VALIDATE_URL)) {
            $response = Http::timeout(30)->get($urlOrPath);

            if (! $response->successful()) {
                throw new \RuntimeException("Failed to fetch content from URL: {$urlOrPath}");
            }

            return $response->body();
        }

        // Jeśli to ścieżka lokalna
        if (file_exists($urlOrPath)) {
            return file_get_contents($urlOrPath);
        }

        throw new \InvalidArgumentException("Invalid URL or file path: {$urlOrPath}");
    }

    /**
     * Pre-processing zawartości (HTML/TSX).
     *
     * @param  string  $content  Zawartość pliku
     * @param  string  $filePath  Ścieżka do pliku
     * @return string Przetworzona zawartość
     */
    private function preprocessContent(string $content, string $filePath): string
    {
        // Jeśli to TSX/TS, zostaw jak jest (AI może analizować kod)
        if (str_ends_with($filePath, '.tsx') || str_ends_with($filePath, '.ts')) {
            return $content;
        }

        // Jeśli to HTML, przetwórz
        // Usuń komentarze
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        return $content;
    }

    /**
     * Analizuj zawartość przez Claude API.
     *
     * @param  string  $content  Zawartość (HTML/TSX)
     * @param  string  $projectName  Nazwa projektu
     * @param  string  $filePath  Ścieżka do pliku
     * @return array<string, mixed> Analiza
     */
    private function analyzeWithClaude(string $content, string $projectName, string $filePath = ''): array
    {
        $systemPrompt = $this->buildAnalysisSystemPrompt();
        $userPrompt = $this->buildAnalysisUserPrompt($content, $projectName, $filePath);

        $response = Http::timeout(120) // 2 minuty dla długich analiz
            ->withHeaders([
                'x-api-key' => config('services.anthropic.api_key', env('ANTHROPIC_API_KEY')),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 8192,
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();
            
            Log::error('Claude API request failed', [
                'status' => $statusCode,
                'body' => $errorBody,
                'project' => $projectName,
            ]);
            
            throw new \RuntimeException("Claude API error (HTTP {$statusCode}): {$errorBody}");
        }

        $responseData = $response->json();
        $content = $responseData['content'][0]['text'] ?? '';

        // Parsuj JSON z odpowiedzi
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        $analysis = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse analysis JSON: '.json_last_error_msg());
        }

        return $analysis;
    }

    /**
     * Buduj system prompt dla analizy.
     */
    private function buildAnalysisSystemPrompt(): string
    {
        return <<<'PROMPT'
Jesteś ekspertem od analizy szablonów stron internetowych i projektowania Content Blocks dla systemu CMS.

Twoim zadaniem jest przeanalizować podany szablon HTML i wygenerować szczegółową analizę obejmującą:

1. **Sekcje strony** - zidentyfikuj główne sekcje (Hero, Features, Pricing, Contact, etc.)
2. **Komponenty** - rozpoznaj powtarzające się komponenty (Card, Form, Gallery, etc.)
3. **Content Blocks** - określ które elementy powinny być zarządzane przez CMS jako bloki
4. **Struktura danych** - zaproponuj strukturę schema dla ContentBlocks

⚠️ WAŻNE: TEKSTY Z SZABLONU SĄ ŚWIĘTE!
- Wszystkie teksty (headings, paragraphs, buttons) zostały zaakceptowane przez klienta
- NIE WOLNO ich zmieniać, modyfikować, tłumaczyć ani poprawiać
- Content Blocks muszą przechowywać DOKŁADNIE te same teksty co w szablonie

Odpowiedź zwróć w formacie JSON z następującą strukturą:
{
  "sections": [...],
  "components": [...],
  "blocks": [...],
  "contentTypes": [...],
  "summary": "..."
}

Używaj nazw polskich dla opisów, ale angielskich dla identyfikatorów i nazw pól.
PROMPT;
    }

    /**
     * Buduj user prompt dla analizy.
     *
     * @param  string  $content  Zawartość (HTML/TSX)
     * @param  string  $projectName  Nazwa projektu
     * @param  string  $filePath  Ścieżka do pliku
     * @return string Prompt
     */
    private function buildAnalysisUserPrompt(string $content, string $projectName, string $filePath = ''): string
    {
        // Ogranicz długość (max 50000 znaków)
        $contentPreview = mb_substr($content, 0, 50000);
        if (mb_strlen($content) > 50000) {
            $contentPreview .= "\n...(truncated)";
        }

        $fileType = 'HTML';
        $codeBlock = 'html';
        if (str_ends_with($filePath, '.tsx') || str_ends_with($filePath, '.ts')) {
            $fileType = 'TypeScript/TSX';
            $codeBlock = 'tsx';
        }

        return <<<PROMPT
Przeanalizuj poniższy szablon {$fileType} dla projektu "{$projectName}".

**Plik:** {$filePath}
**Zawartość:**
```{$codeBlock}
{$contentPreview}
```

Wygeneruj pełną analizę w formacie JSON zgodnym z interfejsem TemplateAnalysis.
PROMPT;
    }

    /**
     * Ekstrahuj komponenty z analizy.
     *
     * @param  array<string, mixed>  $analysis  Analiza
     * @return array<int, array<string, mixed>>
     */
    private function extractComponents(array $analysis): array
    {
        return $analysis['components'] ?? [];
    }

    /**
     * Ekstrahuj bloki z analizy.
     *
     * @param  array<string, mixed>  $analysis  Analiza
     * @return array<int, array<string, mixed>>
     */
    private function extractBlocks(array $analysis): array
    {
        $blocks = [];

        // Konwertuj komponenty na bloki
        foreach ($analysis['components'] ?? [] as $component) {
            $blocks[] = [
                'name' => $component['name'] ?? 'Unnamed',
                'category' => $component['category'] ?? 'general',
                'description' => $component['description'] ?? '',
                'defaultData' => $component['defaultData'] ?? [],
                'schema' => $component['schema'] ?? [],
            ];
        }

        // Dodaj dedykowane bloki z analizy
        foreach ($analysis['blocks'] ?? [] as $block) {
            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Buduj schema dla ContentBlock.
     *
     * @param  array<string, mixed>  $blockData  Dane bloku
     * @return array<string, mixed> Schema
     */
    private function buildContentBlockSchema(array $blockData): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        // Jeśli schema jest już zdefiniowana, użyj jej
        if (isset($blockData['schema']) && is_array($blockData['schema'])) {
            return $blockData['schema'];
        }

        // W przeciwnym razie wygeneruj podstawową schema
        if (isset($blockData['fields']) && is_array($blockData['fields'])) {
            foreach ($blockData['fields'] as $field) {
                $schema['properties'][$field['name'] ?? 'field'] = [
                    'type' => $field['type'] ?? 'string',
                    'title' => $field['title'] ?? '',
                    'description' => $field['description'] ?? '',
                ];

                if (isset($field['required']) && $field['required']) {
                    $schema['required'][] = $field['name'] ?? 'field';
                }
            }
        }

        return $schema;
    }

    /**
     * Zbierz wszystkie komponenty z szablonu.
     *
     * @param  string  $templatePath  Pełna ścieżka do szablonu
     * @return array<int, array<string, mixed>>
     */
    private function collectComponents(string $templatePath): array
    {
        $components = [];
        $componentsPaths = [
            "{$templatePath}/src/components",
            "{$templatePath}/components",
        ];

        foreach ($componentsPaths as $componentsPath) {
            if (! is_dir($componentsPath)) {
                continue;
            }

            $files = \Illuminate\Support\Facades\File::allFiles($componentsPath);
            foreach ($files as $file) {
                if (! in_array($file->getExtension(), ['tsx', 'ts', 'jsx', 'js'])) {
                    continue;
                }

                $relativePath = str_replace("{$templatePath}/", '', $file->getPathname());
                $content = file_get_contents($file->getPathname());
                
                // Ogranicz długość (max 5000 znaków na komponent)
                $contentPreview = mb_substr($content, 0, 5000);
                if (mb_strlen($content) > 5000) {
                    $contentPreview .= "\n...(truncated)";
                }

                $components[] = [
                    'name' => $file->getFilenameWithoutExtension(),
                    'path' => $relativePath,
                    'content' => $contentPreview,
                ];
            }
        }

        return $components;
    }

    /**
     * Zbierz wszystkie strony z szablonu.
     *
     * @param  string  $templatePath  Pełna ścieżka do szablonu
     * @return array<int, array<string, mixed>>
     */
    private function collectPages(string $templatePath): array
    {
        $pages = [];
        $pagesPaths = [
            "{$templatePath}/src/app",
            "{$templatePath}/app",
            "{$templatePath}/src/pages",
            "{$templatePath}/pages",
        ];

        foreach ($pagesPaths as $pagesPath) {
            if (! is_dir($pagesPath)) {
                continue;
            }

            $files = \Illuminate\Support\Facades\File::allFiles($pagesPath);
            foreach ($files as $file) {
                if ($file->getFilename() !== 'page.tsx' && $file->getFilename() !== 'page.ts' 
                    && $file->getFilename() !== 'index.tsx' && $file->getFilename() !== 'index.ts') {
                    continue;
                }

                $relativePath = str_replace("{$templatePath}/", '', $file->getPathname());
                $content = file_get_contents($file->getPathname());
                
                // Ogranicz długość
                $contentPreview = mb_substr($content, 0, 10000);
                if (mb_strlen($content) > 10000) {
                    $contentPreview .= "\n...(truncated)";
                }

                $pages[] = [
                    'path' => $relativePath,
                    'route' => $this->extractRoute($relativePath),
                    'content' => $contentPreview,
                ];
            }
        }

        return $pages;
    }

    /**
     * Zbierz pliki z danymi (JSON, TS).
     *
     * @param  string  $templatePath  Pełna ścieżka do szablonu
     * @return array<int, array<string, mixed>>
     */
    private function collectDataFiles(string $templatePath): array
    {
        $dataFiles = [];
        $dataPaths = [
            "{$templatePath}/data",
            "{$templatePath}/src/data",
            "{$templatePath}/lib",
            "{$templatePath}/src/lib",
        ];

        foreach ($dataPaths as $dataPath) {
            if (! is_dir($dataPath)) {
                continue;
            }

            $files = \Illuminate\Support\Facades\File::allFiles($dataPath);
            foreach ($files as $file) {
                if (! in_array($file->getExtension(), ['json', 'ts', 'tsx', 'js'])) {
                    continue;
                }

                $relativePath = str_replace("{$templatePath}/", '', $file->getPathname());
                $content = file_get_contents($file->getPathname());
                
                // Ogranicz długość
                $contentPreview = mb_substr($content, 0, 5000);
                if (mb_strlen($content) > 5000) {
                    $contentPreview .= "\n...(truncated)";
                }

                $dataFiles[] = [
                    'path' => $relativePath,
                    'content' => $contentPreview,
                ];
            }
        }

        return $dataFiles;
    }

    /**
     * Wyekstraktuj route z ścieżki pliku.
     */
    private function extractRoute(string $filePath): string
    {
        // app/page.tsx -> /
        // app/about/page.tsx -> /about
        // pages/index.tsx -> /
        // pages/about.tsx -> /about

        if (str_contains($filePath, 'app/')) {
            $route = str_replace(['app/', '/page.tsx', '/page.ts'], '', $filePath);
            return $route === '' ? '/' : '/'.$route;
        }

        if (str_contains($filePath, 'pages/')) {
            $route = str_replace(['pages/', 'index.tsx', 'index.ts'], '', $filePath);
            $route = str_replace('.tsx', '', $route);
            $route = str_replace('.ts', '', $route);
            return $route === '' ? '/' : '/'.$route;
        }

        return '/';
    }

    /**
     * Zbuduj kontekst struktury dla analizy AI.
     *
     * @param  string  $templatePath  Pełna ścieżka
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<int, array<string, mixed>>  $dataFiles
     * @return string Kontekst
     */
    private function buildStructureContext(
        string $templatePath,
        array $components,
        array $pages,
        array $dataFiles
    ): string {
        $context = "## Struktura szablonu Next.js\n\n";
        $componentsCount = count($components);
        $context .= "### Komponenty ({$componentsCount})\n\n";
        
        foreach (array_slice($components, 0, 10) as $component) { // Max 10 komponentów
            $context .= "**{$component['name']}** ({$component['path']})\n";
            $context .= "```tsx\n{$component['content']}\n```\n\n";
        }

        if (count($components) > 10) {
            $remaining = count($components) - 10;
            $context .= "... i {$remaining} więcej komponentów\n\n";
        }

        $pagesCount = count($pages);
        $context .= "### Strony ({$pagesCount})\n\n";
        foreach ($pages as $page) {
            $context .= "**Route:** {$page['route']} ({$page['path']})\n";
            $context .= "```tsx\n{$page['content']}\n```\n\n";
        }

        if (! empty($dataFiles)) {
            $dataFilesCount = count($dataFiles);
            $context .= "### Pliki danych ({$dataFilesCount})\n\n";
            foreach (array_slice($dataFiles, 0, 5) as $dataFile) { // Max 5 plików
                $context .= "**{$dataFile['path']}**\n";
                $context .= "```\n{$dataFile['content']}\n```\n\n";
            }
        }

        return $context;
    }

    /**
     * Analizuj strukturę przez Claude API.
     *
     * @param  string  $context  Kontekst struktury
     * @param  string  $projectName  Nazwa projektu
     * @return array<string, mixed> Analiza
     */
    private function analyzeStructureWithClaude(string $context, string $projectName): array
    {
        $systemPrompt = $this->buildStructureAnalysisSystemPrompt();
        $userPrompt = $this->buildStructureAnalysisUserPrompt($context, $projectName);

        $response = Http::timeout(180) // 3 minuty dla analizy całej struktury (wiele plików)
            ->withHeaders([
                'x-api-key' => config('services.anthropic.api_key', env('ANTHROPIC_API_KEY')),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 16384,
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();
            
            Log::error('Claude API request failed', [
                'status' => $statusCode,
                'body' => $errorBody,
                'project' => $projectName,
            ]);
            
            throw new \RuntimeException("Claude API error (HTTP {$statusCode}): {$errorBody}");
        }

        $responseData = $response->json();
        $content = $responseData['content'][0]['text'] ?? '';

        // Parsuj JSON z odpowiedzi
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        $analysis = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse analysis JSON: '.json_last_error_msg());
        }

        return $analysis;
    }

    /**
     * Buduj system prompt dla analizy struktury.
     */
    private function buildStructureAnalysisSystemPrompt(): string
    {
        return <<<'PROMPT'
Jesteś ekspertem od analizy szablonów Next.js i projektowania Content Blocks dla systemu CMS.

Twoim zadaniem jest przeanalizować strukturę szablonu Next.js i wygenerować szczegółową analizę obejmującą:

1. **Sekcje strony** - zidentyfikuj główne sekcje (Hero, Features, Pricing, Contact, etc.)
2. **Komponenty** - rozpoznaj wszystkie komponenty i ich funkcjonalność
3. **Wymagania danych** - określ jakie dane są potrzebne dla każdego komponentu/sekcji
4. **Content Blocks** - zaproponuj strukturę ContentBlocks dla CMS
5. **Mapowanie danych** - określ jak dane z CMS powinny być mapowane do komponentów

⚠️ WAŻNE: TEKSTY Z SZABLONU SĄ ŚWIĘTE!
- Wszystkie teksty zostały zaakceptowane przez klienta
- NIE WOLNO ich zmieniać, modyfikować, tłumaczyć ani poprawiać
- Content Blocks muszą przechowywać DOKŁADNIE te same teksty co w szablonie

Odpowiedź zwróć w formacie JSON:
{
  "sections": [...],
  "components": [...],
  "dataRequirements": [
    {
      "component": "Hero",
      "fields": [
        {"name": "title", "type": "string", "required": true},
        {"name": "description", "type": "text", "required": true}
      ]
    }
  ],
  "blocks": [...],
  "contentTypes": [...],
  "summary": "..."
}

Używaj nazw polskich dla opisów, ale angielskich dla identyfikatorów i nazw pól.
PROMPT;
    }

    /**
     * Buduj user prompt dla analizy struktury.
     */
    private function buildStructureAnalysisUserPrompt(string $context, string $projectName): string
    {
        return <<<PROMPT
Przeanalizuj poniższą strukturę szablonu Next.js dla projektu "{$projectName}".

{$context}

Wygeneruj pełną analizę w formacie JSON zgodnym z interfejsem TemplateAnalysis.
PROMPT;
    }
}
