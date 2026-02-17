<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use App\Modules\Generator\Models\GeneratedTemplate;
use App\Modules\Generator\Models\Template;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Serwis do konwersji GeneratedTemplate na Template Next.js z plikami.
 */
final class TemplateFromGeneratedService
{
    /**
     * Konwertuj GeneratedTemplate na Template Next.js i zapisz pliki.
     *
     * @param  GeneratedTemplate  $generatedTemplate  Wygenerowany szablon AI
     * @param  string|null  $name  Nazwa szablonu (opcjonalna)
     * @param  string|null  $slug  Slug szablonu (opcjonalny)
     * @return Template Utworzony szablon
     */
    public function createTemplateFromGenerated(
        GeneratedTemplate $generatedTemplate,
        ?string $name = null,
        ?string $slug = null
    ): Template {
        if ($generatedTemplate->status !== 'completed') {
            throw new \RuntimeException('GeneratedTemplate musi być w statusie completed');
        }

        $generatedCode = $generatedTemplate->generated_code ?? [];
        if (empty($generatedCode['components'])) {
            throw new \RuntimeException('Brak komponentów w wygenerowanym kodzie');
        }

        // Wygeneruj nazwę i slug
        $templateName = $name ?? $this->extractName($generatedCode) ?? 'Wygenerowany szablon '.now()->format('Y-m-d H:i');
        $templateSlug = $slug ?? Str::slug($templateName);

        // Utwórz katalog dla szablonu
        $templatesDir = base_path('templates');
        $templatePath = "{$templatesDir}/{$templateSlug}";

        if (File::exists($templatePath)) {
            // Jeśli katalog istnieje, dodaj timestamp
            $templateSlug = $templateSlug.'-'.now()->timestamp;
            $templatePath = "{$templatesDir}/{$templateSlug}";
        }

        File::makeDirectory($templatePath, 0755, true);

        // Utwórz strukturę Next.js
        $this->createNextJsStructure($templatePath, $generatedCode);

        // Utwórz rekord Template w bazie
        $template = Template::create([
            'tenant_id' => $generatedTemplate->tenant_id,
            'name' => $templateName,
            'slug' => $templateSlug,
            'directory_path' => "templates/{$templateSlug}",
            'category' => $this->extractCategory($generatedCode) ?? 'other',
            'tech_stack' => $this->extractTechStack($generatedCode),
            'description' => $this->extractDescription($generatedCode) ?? "Szablon wygenerowany przez AI z promptu: {$generatedTemplate->prompt}",
            'metadata' => [
                'generated_template_id' => $generatedTemplate->id,
                'model' => $generatedTemplate->model,
                'components' => $generatedCode['components'] ?? [],
                'metadata' => $generatedCode['metadata'] ?? [],
            ],
            'tags' => $this->extractTags($generatedCode),
            'is_active' => true,
            'is_premium' => false,
        ]);

        Log::info('Template created from GeneratedTemplate', [
            'generated_template_id' => $generatedTemplate->id,
            'template_id' => $template->id,
            'template_path' => $templatePath,
        ]);

        return $template;
    }

    /**
     * Utwórz strukturę Next.js z wygenerowanymi komponentami.
     *
     * @param  string  $templatePath  Ścieżka do katalogu szablonu
     * @param  array<string, mixed>  $generatedCode  Wygenerowany kod
     */
    private function createNextJsStructure(string $templatePath, array $generatedCode): void
    {
        $components = $generatedCode['components'] ?? [];
        $metadata = $generatedCode['metadata'] ?? [];

        // Utwórz katalog src/components
        $componentsDir = "{$templatePath}/src/components";
        File::makeDirectory($componentsDir, 0755, true);

        // Zapisz komponenty jako pliki TSX
        foreach ($components as $component) {
            $componentName = $component['name'] ?? 'Component';
            $componentCode = $component['code'] ?? '';
            $componentType = $component['type'] ?? 'tsx';

            $fileName = Str::slug($componentName).'.'.$componentType;
            $filePath = "{$componentsDir}/{$fileName}";

            File::put($filePath, $componentCode);
        }

        // Utwórz podstawową strukturę Next.js
        $this->createPackageJson($templatePath, $metadata);
        $this->createTsConfig($templatePath);
        $this->createNextConfig($templatePath);
        $this->createAppLayout($templatePath);
        $this->createAppPage($templatePath, $components);
        $this->createTailwindConfig($templatePath);
    }

    /**
     * Utwórz package.json.
     */
    private function createPackageJson(string $templatePath, array $metadata): void
    {
        $dependencies = $metadata['dependencies'] ?? [];
        $techStack = $metadata['tech_stack'] ?? ['next.js', 'typescript', 'tailwindcss'];

        $packageJson = [
            'name' => basename($templatePath),
            'version' => '1.0.0',
            'private' => true,
            'scripts' => [
                'dev' => 'next dev',
                'build' => 'next build',
                'start' => 'next start',
                'lint' => 'next lint',
            ],
            'dependencies' => [
                'next' => '^14.0.0',
                'react' => '^18.0.0',
                'react-dom' => '^18.0.0',
            ],
            'devDependencies' => [
                '@types/node' => '^20.0.0',
                '@types/react' => '^18.0.0',
                '@types/react-dom' => '^18.0.0',
                'typescript' => '^5.0.0',
                'tailwindcss' => '^3.0.0',
                'postcss' => '^8.0.0',
                'autoprefixer' => '^10.0.0',
            ],
        ];

        // Dodaj dodatkowe zależności z komponentów
        if (in_array('framer-motion', $dependencies)) {
            $packageJson['dependencies']['framer-motion'] = '^10.0.0';
        }

        File::put(
            "{$templatePath}/package.json",
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Utwórz tsconfig.json.
     */
    private function createTsConfig(string $templatePath): void
    {
        $tsConfig = [
            'compilerOptions' => [
                'target' => 'ES2020',
                'lib' => ['dom', 'dom.iterable', 'esnext'],
                'allowJs' => true,
                'skipLibCheck' => true,
                'strict' => true,
                'noEmit' => true,
                'esModuleInterop' => true,
                'module' => 'esnext',
                'moduleResolution' => 'bundler',
                'resolveJsonModule' => true,
                'isolatedModules' => true,
                'jsx' => 'preserve',
                'incremental' => true,
                'plugins' => [
                    [
                        'name' => 'next',
                    ],
                ],
                'paths' => [
                    '@/*' => ['./src/*'],
                ],
            ],
            'include' => ['next-env.d.ts', '**/*.ts', '**/*.tsx', '.next/types/**/*.ts'],
            'exclude' => ['node_modules'],
        ];

        File::put(
            "{$templatePath}/tsconfig.json",
            json_encode($tsConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Utwórz next.config.mjs.
     */
    private function createNextConfig(string $templatePath): void
    {
        $config = <<<'CONFIG'
/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
};

export default nextConfig;
CONFIG;

        File::put("{$templatePath}/next.config.mjs", $config);
    }

    /**
     * Utwórz app/layout.tsx.
     */
    private function createAppLayout(string $templatePath): void
    {
        $appDir = "{$templatePath}/src/app";
        File::makeDirectory($appDir, 0755, true);

        $layout = <<<'LAYOUT'
import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Generated Template',
  description: 'Template generated by AI',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="pl">
      <body>{children}</body>
    </html>
  );
}
LAYOUT;

        File::put("{$appDir}/layout.tsx", $layout);
    }

    /**
     * Utwórz app/page.tsx z komponentami.
     */
    private function createAppPage(string $templatePath, array $components): void
    {
        $appDir = "{$templatePath}/src/app";

        $imports = [];
        $renders = [];

        foreach ($components as $component) {
            $componentName = $component['name'] ?? 'Component';
            $fileName = Str::slug($componentName);
            $imports[] = "import {$componentName} from '@/components/{$fileName}';";
            $renders[] = "      <{$componentName} />";
        }

        $importsString = ! empty($imports) ? implode("\n", $imports) : '// No components';
        $rendersString = ! empty($renders) ? implode("\n", $renders) : '      <div>Brak komponentów</div>';

        $page = $importsString."\n\n".'export default function Home() {'."\n".'  return ('."\n".'    <main>'."\n".$rendersString."\n".'    </main>'."\n".'  );'."\n".'}';

        File::put("{$appDir}/page.tsx", $page);
    }

    /**
     * Utwórz tailwind.config.ts.
     */
    private function createTailwindConfig(string $templatePath): void
    {
        $config = <<<'CONFIG'
import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};

export default config;
CONFIG;

        File::put("{$templatePath}/tailwind.config.ts", $config);

        // Utwórz globals.css
        $globalsCss = <<<'CSS'
@tailwind base;
@tailwind components;
@tailwind utilities;
CSS;

        File::put("{$templatePath}/src/app/globals.css", $globalsCss);

        // Utwórz postcss.config.mjs
        $postcss = <<<'POSTCSS'
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
};
POSTCSS;

        File::put("{$templatePath}/postcss.config.mjs", $postcss);
    }

    /**
     * Wyciągnij nazwę z wygenerowanego kodu.
     */
    private function extractName(array $generatedCode): ?string
    {
        return $generatedCode['metadata']['name'] ?? null;
    }

    /**
     * Wyciągnij kategorię z wygenerowanego kodu.
     */
    private function extractCategory(array $generatedCode): ?string
    {
        return $generatedCode['metadata']['category'] ?? null;
    }

    /**
     * Wyciągnij tech stack z wygenerowanego kodu.
     */
    private function extractTechStack(array $generatedCode): array
    {
        $techStack = $generatedCode['metadata']['tech_stack'] ?? [];
        if (is_array($techStack)) {
            return $techStack;
        }

        return ['Next.js', 'TypeScript', 'Tailwind CSS'];
    }

    /**
     * Wyciągnij opis z wygenerowanego kodu.
     */
    private function extractDescription(array $generatedCode): ?string
    {
        return $generatedCode['metadata']['description'] ?? null;
    }

    /**
     * Wyciągnij tagi z wygenerowanego kodu.
     */
    private function extractTags(array $generatedCode): array
    {
        $tags = $generatedCode['metadata']['tags'] ?? [];
        if (is_array($tags)) {
            return $tags;
        }

        return [];
    }
}
