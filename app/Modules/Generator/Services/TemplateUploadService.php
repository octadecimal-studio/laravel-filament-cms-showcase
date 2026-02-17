<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Serwis do przesyłania i rozpakowania folderów szablonów.
 *
 * Obsługuje:
 * - Upload ZIP z folderem szablonu
 * - Rozpakowanie do templates/
 * - Walidację struktury Next.js
 */
final class TemplateUploadService
{
    /**
     * Prześlij i rozpakuj folder szablonu z ZIP.
     *
     * @param  UploadedFile  $zipFile  Plik ZIP z folderem szablonu
     * @param  string|null  $targetName  Opcjonalna nazwa docelowego katalogu
     * @return string Ścieżka względem templates/ (np. "pewny-facet")
     */
    public function uploadAndExtract(UploadedFile $zipFile, ?string $targetName = null): string
    {
        // Waliduj typ pliku
        if ($zipFile->getMimeType() !== 'application/zip' && $zipFile->getClientOriginalExtension() !== 'zip') {
            throw new \InvalidArgumentException('Plik musi być w formacie ZIP');
        }

        // Upewnij się, że katalog templates/ istnieje
        $templatesDir = base_path('templates');
        if (! File::exists($templatesDir)) {
            File::makeDirectory($templatesDir, 0755, true);
            Log::info('Created templates directory', ['path' => $templatesDir]);
        }

        // Określ nazwę docelowego katalogu
        $templateName = $targetName ?? $this->extractTemplateName($zipFile);
        $targetPath = base_path("templates/{$templateName}");

        // Sprawdź czy katalog już istnieje
        if (File::exists($targetPath)) {
            throw new \RuntimeException("Katalog szablonu już istnieje: {$templateName}");
        }

        // Utwórz tymczasowy katalog dla rozpakowania
        $tempDir = storage_path("app/temp/templates/".uniqid('template_', true));
        File::makeDirectory($tempDir, 0755, true);

        try {
            // Rozpakuj ZIP do tymczasowego katalogu
            $zip = new ZipArchive;
            $result = $zip->open($zipFile->getRealPath());

            if ($result !== true) {
                throw new \RuntimeException("Nie można otworzyć pliku ZIP: {$result}");
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Znajdź główny katalog szablonu (może być bezpośrednio w ZIP lub w podkatalogu)
            $extractedPath = $this->findTemplateRoot($tempDir);

            // Waliduj strukturę Next.js
            $this->validateTemplateStructure($extractedPath);

            // Przenieś do templates/ używając copyDirectory (bardziej niezawodne niż moveDirectory)
            if (File::exists($targetPath)) {
                throw new \RuntimeException("Katalog docelowy już istnieje: {$targetPath}");
            }

            // Skopiuj katalog
            if (! File::copyDirectory($extractedPath, $targetPath)) {
                throw new \RuntimeException("Nie można skopiować katalogu z {$extractedPath} do {$targetPath}");
            }

            // Sprawdź czy katalog faktycznie istnieje po skopiowaniu
            if (! File::exists($targetPath)) {
                throw new \RuntimeException("Katalog nie został utworzony po skopiowaniu: {$targetPath}");
            }

            // Sprawdź czy package.json istnieje w docelowym katalogu (potwierdzenie poprawnego skopiowania)
            if (! File::exists("{$targetPath}/package.json")) {
                throw new \RuntimeException("package.json nie został skopiowany do {$targetPath}");
            }

            // Usuń źródłowy katalog po udanym skopiowaniu
            if (File::exists($extractedPath)) {
                File::deleteDirectory($extractedPath);
            }

            Log::info('Template uploaded and extracted', [
                'template_name' => $templateName,
                'target_path' => $targetPath,
                'extracted_path' => $extractedPath,
            ]);

            return $templateName;
        } catch (\Exception $e) {
            // Wyczyść w przypadku błędu
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            if (File::exists($targetPath)) {
                File::deleteDirectory($targetPath);
            }

            throw $e;
        } finally {
            // Wyczyść tymczasowy katalog
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Wyekstraktuj nazwę szablonu z nazwy pliku.
     */
    private function extractTemplateName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // Usuń rozszerzenia i nieprawidłowe znaki
        $name = Str::slug($name);
        
        if (empty($name)) {
            $name = 'template-'.uniqid();
        }

        return $name;
    }

    /**
     * Znajdź główny katalog szablonu w rozpakowanym ZIP.
     *
     * ZIP może zawierać:
     * - Pliki bezpośrednio w root
     * - Jeden folder z wszystkimi plikami
     * - Wiele folderów (bierzemy pierwszy z package.json)
     */
    private function findTemplateRoot(string $extractedPath): string
    {
        $files = File::files($extractedPath);
        $directories = File::directories($extractedPath);

        // Jeśli jest package.json bezpośrednio w root, to jest to katalog szablonu
        foreach ($files as $file) {
            if ($file->getFilename() === 'package.json') {
                return $extractedPath;
            }
        }

        // Jeśli jest jeden katalog, sprawdź czy ma package.json
        if (count($directories) === 1) {
            $dir = $directories[0];
            if (File::exists("{$dir}/package.json")) {
                return $dir;
            }
        }

        // Szukaj katalogu z package.json
        foreach ($directories as $dir) {
            if (File::exists("{$dir}/package.json")) {
                return $dir;
            }

            // Rekurencyjnie szukaj w podkatalogach (max 2 poziomy)
            $subdirs = File::directories($dir);
            foreach ($subdirs as $subdir) {
                if (File::exists("{$subdir}/package.json")) {
                    return $subdir;
                }
            }
        }

        // Jeśli nie znaleziono, zwróć root
        return $extractedPath;
    }

    /**
     * Waliduj strukturę szablonu Next.js.
     *
     * @throws \RuntimeException Jeśli struktura jest nieprawidłowa
     */
    private function validateTemplateStructure(string $templatePath): void
    {
        // Musi mieć package.json
        if (! File::exists("{$templatePath}/package.json")) {
            throw new \RuntimeException('Szablon musi zawierać plik package.json');
        }

        // Sprawdź czy to Next.js
        $packageJson = json_decode(File::get("{$templatePath}/package.json"), true);
        $deps = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? []
        );

        if (! isset($deps['next']) && ! isset($deps['react'])) {
            throw new \RuntimeException('Szablon musi być projektem Next.js lub React');
        }

        // Musi mieć strukturę Next.js (app/ lub pages/)
        $hasApp = File::exists("{$templatePath}/src/app") || File::exists("{$templatePath}/app");
        $hasPages = File::exists("{$templatePath}/src/pages") || File::exists("{$templatePath}/pages");

        if (! $hasApp && ! $hasPages) {
            throw new \RuntimeException('Szablon musi mieć katalog app/ lub pages/ (struktura Next.js)');
        }
    }
}
