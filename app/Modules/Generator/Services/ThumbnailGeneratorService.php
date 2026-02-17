<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use App\Modules\Generator\Models\Template;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Serwis do generowania miniatur szablonów przy użyciu Playwright.
 */
final class ThumbnailGeneratorService
{
    /**
     * Generuj miniaturkę dla szablonu.
     *
     * @param  Template  $template  Szablon
     * @param  int  $width  Szerokość miniaturki (domyślnie 400px)
     * @param  int  $height  Wysokość miniaturki (domyślnie 300px)
     * @return string|null Ścieżka do wygenerowanej miniaturki lub null
     */
    public function generateThumbnail(Template $template, int $width = 400, int $height = 300): ?string
    {
        try {
            // Sprawdź czy szablon ma preview_url lub można go zbudować
            $previewUrl = $template->preview_url ?? $template->getPreviewUrl();
            
            if (! $previewUrl) {
                Log::warning('Template has no preview URL for thumbnail generation', [
                    'template_id' => $template->id,
                ]);

                return null;
            }

            // Wywołaj Node.js script z Playwright
            $thumbnailPath = $this->generateWithPlaywright($previewUrl, $template->id, $width, $height);

            if ($thumbnailPath && file_exists($thumbnailPath)) {
                // Zapisz do storage
                $storagePath = "templates/thumbnails/{$template->id}.png";
                Storage::disk('public')->put($storagePath, file_get_contents($thumbnailPath));

                // Usuń tymczasowy plik
                @unlink($thumbnailPath);

                // Zaktualizuj template
                $template->update([
                    'thumbnail_url' => "storage/{$storagePath}",
                ]);

                return $storagePath;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Thumbnail generation failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generuj miniaturkę przy użyciu Playwright (Node.js).
     *
     * @param  string  $url  URL do zrzutu ekranu
     * @param  string  $templateId  ID szablonu
     * @param  int  $width  Szerokość
     * @param  int  $height  Wysokość
     * @return string|null Ścieżka do wygenerowanego pliku
     */
    private function generateWithPlaywright(string $url, string $templateId, int $width, int $height): ?string
    {
        $scriptPath = base_path('scripts/generate-thumbnail.js');
        $outputPath = storage_path("app/temp/thumbnail-{$templateId}.png");

        // Utwórz katalog jeśli nie istnieje
        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Sprawdź czy Node.js i Playwright są dostępne
        if (! $this->isPlaywrightAvailable()) {
            Log::warning('Playwright is not available, skipping thumbnail generation');

            return null;
        }

        // Wywołaj Node.js script
        $command = sprintf(
            'node %s --url=%s --output=%s --width=%d --height=%d',
            escapeshellarg($scriptPath),
            escapeshellarg($url),
            escapeshellarg($outputPath),
            $width,
            $height
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('Playwright script failed', [
                'command' => $command,
                'output' => $output,
                'return_code' => $returnCode,
            ]);

            return null;
        }

        return file_exists($outputPath) ? $outputPath : null;
    }

    /**
     * Sprawdź czy Playwright jest dostępny.
     */
    private function isPlaywrightAvailable(): bool
    {
        // Sprawdź czy Node.js jest dostępny
        exec('node --version', $nodeOutput, $nodeReturnCode);
        if ($nodeReturnCode !== 0) {
            return false;
        }

        // Sprawdź czy Playwright jest zainstalowany
        $playwrightPath = base_path('node_modules/.bin/playwright');
        if (! file_exists($playwrightPath)) {
            // Spróbuj zainstalować Playwright
            $this->installPlaywright();

            return file_exists($playwrightPath);
        }

        return true;
    }

    /**
     * Zainstaluj Playwright (jeśli nie jest zainstalowany).
     */
    private function installPlaywright(): void
    {
        $packageJsonPath = base_path('package.json');
        
        if (! file_exists($packageJsonPath)) {
            // Utwórz podstawowy package.json
            file_put_contents($packageJsonPath, json_encode([
                'name' => 'octadecimal-studio',
                'version' => '1.0.0',
                'private' => true,
                'dependencies' => [
                    'playwright' => '^1.40.0',
                ],
            ], JSON_PRETTY_PRINT));
        }

        // Zainstaluj zależności
        chdir(base_path());
        exec('npm install playwright 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            // Zainstaluj przeglądarki
            exec('npx playwright install chromium 2>&1', $output, $returnCode);
        }
    }
}
