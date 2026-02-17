<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Generator\Models\GeneratedTemplateResource\Pages;

use App\Filament\Resources\Modules\Generator\Models\GeneratedTemplateResource;
use App\Modules\Generator\Models\GeneratedTemplate;
use App\Modules\Generator\Services\TemplateFromGeneratedService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ViewGeneratedTemplate extends ViewRecord
{
    protected static string $resource = GeneratedTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save_as_template')
                ->label('Zapisz jako Template Next.js')
                ->icon('heroicon-o-folder-plus')
                ->color('success')
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Nazwa szablonu')
                        ->required()
                        ->default(fn (GeneratedTemplate $record): string => $this->extractName($record) ?? 'Wygenerowany szablon'),
                    \Filament\Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->default(fn (GeneratedTemplate $record): string => \Illuminate\Support\Str::slug($this->extractName($record) ?? 'wygenerowany-szablon')),
                ])
                ->action(function (GeneratedTemplate $record, array $data): void {
                    try {
                        $service = app(TemplateFromGeneratedService::class);
                        $template = $service->createTemplateFromGenerated(
                            $record,
                            $data['name'],
                            $data['slug']
                        );

                        Notification::make()
                            ->title('Szablon zapisany')
                            ->body("Szablon został zapisany jako Template Next.js: {$template->name}")
                            ->success()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('view')
                                    ->label('Zobacz szablon')
                                    ->url(\App\Filament\Resources\Modules\Generator\Models\TemplateResource::getUrl('edit', ['record' => $template])),
                            ])
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd zapisu')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (GeneratedTemplate $record): bool => $record->status === 'completed'),
            Actions\Action::make('download_zip')
                ->label('Pobierz jako ZIP')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function (GeneratedTemplate $record) {
                    try {
                        // Utwórz tymczasowy katalog
                        $tempDir = storage_path('app/temp/generated-templates/'.$record->id);
                        File::ensureDirectoryExists($tempDir);
                        File::cleanDirectory($tempDir);

                        // Utwórz strukturę Next.js z wygenerowanego kodu
                        $service = app(TemplateFromGeneratedService::class);
                        $templateName = $this->extractName($record) ?? 'wygenerowany-szablon';
                        $templateSlug = \Illuminate\Support\Str::slug($templateName);
                        $templatePath = "{$tempDir}/{$templateSlug}";

                        // Użyj prywatnej metody do utworzenia struktury
                        $this->createNextJsStructure($templatePath, $record->generated_code ?? []);

                        // Utwórz ZIP
                        $zipPath = storage_path("app/temp/generated-templates/{$record->id}.zip");
                        $zip = new ZipArchive();
                        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                            throw new \RuntimeException('Nie można utworzyć pliku ZIP');
                        }

                        // Dodaj wszystkie pliki do ZIP
                        $files = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($templatePath),
                            \RecursiveIteratorIterator::LEAVES_ONLY
                        );

                        foreach ($files as $file) {
                            if (!$file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen($templatePath) + 1);
                                $zip->addFile($filePath, $relativePath);
                            }
                        }

                        $zip->close();

                        // Zwróć download
                        return \Illuminate\Support\Facades\Response::download($zipPath, "{$templateSlug}.zip")
                            ->deleteFileAfterSend(true);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd pobierania')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return null;
                    }
                })
                ->visible(fn (GeneratedTemplate $record): bool => $record->status === 'completed' && !empty($record->generated_code)),
        ];
    }

    /**
     * Wyciągnij nazwę z wygenerowanego kodu.
     */
    private function extractName(GeneratedTemplate $record): ?string
    {
        $generatedCode = $record->generated_code ?? [];
        return $generatedCode['metadata']['name'] ?? null;
    }

    /**
     * Utwórz strukturę Next.js z wygenerowanego kodu (uproszczona wersja).
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

            $fileName = \Illuminate\Support\Str::slug($componentName).'.'.$componentType;
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
                'plugins' => [['name' => 'next']],
                'paths' => ['@/*' => ['./src/*']],
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
            $fileName = \Illuminate\Support\Str::slug($componentName);
            $imports[] = "import {$componentName} from '@/components/{$fileName}';";
            $renders[] = "      <{$componentName} />";
        }

        $importsString = !empty($imports) ? implode("\n", $imports) : '// No components';
        $rendersString = !empty($renders) ? implode("\n", $renders) : '      <div>Brak komponentów</div>';

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
}
