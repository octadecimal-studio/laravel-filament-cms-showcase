<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Generator\Models\TemplateResource\Pages;

use App\Filament\Resources\Modules\Generator\Models\TemplateResource;
use App\Modules\Generator\Services\TemplateAnalyzerService;
use App\Modules\Generator\Services\TemplateImportService;
use App\Modules\Generator\Services\TemplateParserService;
use App\Modules\Generator\Services\TemplateUploadService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateTemplate extends CreateRecord
{
    protected static string $resource = TemplateResource::class;

    /**
     * Po utworzeniu szablonu, uruchom analizę AI w tle (opcjonalnie).
     * 
     * UWAGA: Analiza AI jest wyłączona aby uniknąć problemów z przekierowaniem.
     * Można ją włączyć później używając joba w tle.
     */
    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Uruchom analizę AI w tle (background job) jeśli szablon został utworzony z ZIP
        if ($record->directory_path) {
            $user = \Illuminate\Support\Facades\Auth::user();
            
            \App\Jobs\AnalyzeTemplateJob::dispatch(
                $record->id,
                $user?->id
            );
            
            \Illuminate\Support\Facades\Log::info('Template analysis job dispatched', [
                'template_id' => $record->id,
                'template_path' => $record->directory_path,
                'user_id' => $user?->id,
            ]);
            
            // Powiadom użytkownika że analiza została uruchomiona w tle
            \Filament\Notifications\Notification::make()
                ->info()
                ->title('Analiza AI uruchomiona')
                ->body('Analiza szablonu została dodana do kolejki. Otrzymasz powiadomienie po zakończeniu.')
                ->send();
        }
    }

    /**
     * Przekierowanie po utworzeniu - na listę zamiast view (bardziej niezawodne).
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Mutate form data before create - rozpakuj ZIP i przeanalizuj szablon.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \RuntimeException('Użytkownik musi być zalogowany');
        }

        // Dla super admina bez tenant_id, użyj pierwszego aktywnego tenanta jako domyślnego
        // Ustaw tenant_id w sesji, aby trait BelongsToTenant mógł go użyć
        if (! $user->tenant_id) {
            /** @var \App\Models\User $user */
            $isSuperAdmin = $user->is_super_admin || (method_exists($user, 'hasRole') && $user->hasRole('super_admin'));
            if ($isSuperAdmin) {
                // Znajdź pierwszy aktywny tenant
                $defaultTenant = \App\Modules\Core\Models\Tenant::where('is_active', true)->first();
                if ($defaultTenant) {
                    // Ustaw w sesji, aby trait BelongsToTenant mógł to użyć
                    session(['tenant_id' => $defaultTenant->id]);
                } else {
                    throw new \RuntimeException('Brak aktywnych tenantów w systemie. Super admin nie może utworzyć szablonu bez tenanta.');
                }
            } else {
                throw new \RuntimeException('Użytkownik musi należeć do tenanta');
            }
        }

        // Jeśli przesłano plik ZIP, rozpakuj go i przeanalizuj
        if (isset($data['template_zip']) && $data['template_zip']) {
            try {
                $zipPath = is_array($data['template_zip']) 
                    ? $data['template_zip'][0] 
                    : $data['template_zip'];

                if (! $zipPath) {
                    throw new \RuntimeException('Ścieżka do pliku ZIP jest pusta');
                }

                // Pobierz pełną ścieżkę do pliku ZIP
                $storage = Storage::disk('local');
                $fullZipPath = $storage->path($zipPath);
                
                if (! file_exists($fullZipPath)) {
                    throw new \RuntimeException("Plik ZIP nie został znaleziony: {$zipPath}");
                }

                // Utwórz UploadedFile z zapisanego pliku
                $zipFile = new UploadedFile(
                    $fullZipPath,
                    basename($fullZipPath),
                    mime_content_type($fullZipPath) ?: 'application/zip',
                    null,
                    true // test mode - plik już istnieje
                );

                // Rozpakuj ZIP do templates/
                // Użyj directory_path jeśli jest ustawione, w przeciwnym razie wygeneruj z nazwy pliku
                $targetName = $data['directory_path'] ?? null;
                $uploadService = app(TemplateUploadService::class);
                
                try {
                    $templateName = $uploadService->uploadAndExtract($zipFile, $targetName);
                } catch (\RuntimeException $e) {
                    // Jeśli katalog już istnieje, spróbuj z unikalną nazwą
                    if (str_contains($e->getMessage(), 'już istnieje')) {
                        $targetName = ($targetName ?? 'template').'-'.uniqid();
                        $templateName = $uploadService->uploadAndExtract($zipFile, $targetName);
                    } else {
                        throw $e;
                    }
                }

                // Ustaw directory_path na faktyczną nazwę używaną przez uploadAndExtract
                $data['directory_path'] = $templateName;
                
                // Sprawdź czy katalog faktycznie istnieje przed parsowaniem
                $fullPath = base_path("templates/{$templateName}");
                if (! \Illuminate\Support\Facades\File::exists($fullPath)) {
                    throw new \RuntimeException("Katalog szablonu nie został utworzony: {$templateName}");
                }

                // Parsuj strukturę szablonu
                $parser = app(TemplateParserService::class);
                $parsed = $parser->parse($templateName);

                // Wypełnij dane z parsowania
                if (empty($data['name'])) {
                    $data['name'] = \Illuminate\Support\Str::title(str_replace(['.', '-', '_'], ' ', $templateName));
                }
                if (empty($data['slug'])) {
                    $data['slug'] = \Illuminate\Support\Str::slug($data['name'] ?? $templateName);
                }

                // Ekstraktuj tech stack
                $dependencies = $parsed['dependencies'] ?? [];
                $deps = array_merge(
                    $dependencies['dependencies'] ?? [],
                    $dependencies['devDependencies'] ?? []
                );
                $techStack = [];
                if (isset($deps['next'])) {
                    $techStack[] = 'Next.js';
                }
                if (isset($deps['react'])) {
                    $techStack[] = 'React';
                }
                if (isset($deps['typescript'])) {
                    $techStack[] = 'TypeScript';
                }
                if (isset($deps['tailwindcss'])) {
                    $techStack[] = 'Tailwind CSS';
                }
                if (empty($data['tech_stack'])) {
                    $data['tech_stack'] = $techStack;
                }

                // Wykryj kategorię
                if (empty($data['category'])) {
                    $data['category'] = $this->detectCategory($templateName);
                }

                // Ustaw metadata
                if (empty($data['metadata'])) {
                    $data['metadata'] = [
                        'components' => $parsed['components'] ?? [],
                        'structure' => $parsed['structure'] ?? [],
                        'styles' => $parsed['styles'] ?? [],
                        'dependencies' => $dependencies,
                    ];
                }

                // Usuń template_zip z danych (nie zapisujemy go w bazie)
                unset($data['template_zip']);

                // Usuń plik tymczasowy
                $storage->delete($zipPath);

                Notification::make()
                    ->success()
                    ->title('Szablon rozpakowany')
                    ->body("Szablon został rozpakowany do templates/{$templateName}.")
                    ->send();

                // Zapisz informację o szablonie do analizy AI po zapisaniu (w afterCreate)
                // Nie uruchamiaj analizy AI tutaj - może to przerwać proces zapisywania
            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('Błąd przetwarzania ZIP')
                    ->body($e->getMessage())
                    ->send();

                // Usuń template_zip z danych nawet jeśli wystąpił błąd
                unset($data['template_zip']);
                
                throw $e;
            }
        }

        return $data;
    }

    /**
     * Wykryj kategorię na podstawie nazwy szablonu.
     */
    private function detectCategory(string $templateName): string
    {
        $name = \Illuminate\Support\Str::lower($templateName);
        
        if (\Illuminate\Support\Str::contains($name, 'portfolio')) {
            return 'portfolio';
        }
        if (\Illuminate\Support\Str::contains($name, 'landing')) {
            return 'landing';
        }
        if (\Illuminate\Support\Str::contains($name, 'corporate') || \Illuminate\Support\Str::contains($name, 'prestige')) {
            return 'corporate';
        }
        if (\Illuminate\Support\Str::contains($name, 'blog')) {
            return 'blog';
        }
        if (\Illuminate\Support\Str::contains($name, 'ecommerce') || \Illuminate\Support\Str::contains($name, 'shop')) {
            return 'ecommerce';
        }

        return 'other';
    }

    /**
     * Przeanalizuj szablon przez AI i wygeneruj ContentBlocks.
     *
     * @param  string  $templatePath  Ścieżka względem templates/
     * @param  string  $projectName  Nazwa projektu
     */
    private function analyzeWithAI(string $templatePath, string $projectName): void
    {
        try {
            $analyzer = app(TemplateAnalyzerService::class);
            
            // Analizuj całą strukturę szablonu
            $analysis = $analyzer->analyzeTemplateStructure($templatePath, $projectName);
            $blocks = $analyzer->generateContentBlocks($analysis);

            Notification::make()
                ->success()
                ->title('Analiza AI zakończona')
                ->body('Utworzono '.count($blocks).' ContentBlocks z analizy struktury szablonu.')
                ->send();
        } catch (\Exception $e) {
            // Nie przerywaj procesu tworzenia jeśli analiza AI się nie powiedzie
            \Illuminate\Support\Facades\Log::error('AI analysis failed', [
                'template' => $templatePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Wyświetl przyjazny komunikat użytkownikowi
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'timed out') || str_contains($errorMessage, 'timeout')) {
                $errorMessage = 'Analiza AI przekroczyła limit czasu. Spróbuj ponownie później lub zmniejsz rozmiar szablonu.';
            } elseif (str_contains($errorMessage, 'Claude API error')) {
                $errorMessage = 'Błąd połączenia z API Claude. Sprawdź klucz API i połączenie internetowe.';
            }

            Notification::make()
                ->danger()
                ->title('Błąd analizy AI')
                ->body($errorMessage)
                ->persistent()
                ->send();
        }
    }

    /**
     * Znajdź główny plik HTML/TSX do analizy.
     */
    private function findMainHtmlFile(string $templatePath): ?string
    {
        // Sprawdź app/page.tsx (Next.js App Router)
        $appPage = "{$templatePath}/src/app/page.tsx";
        if (file_exists($appPage)) {
            return $appPage;
        }

        $appPage = "{$templatePath}/app/page.tsx";
        if (file_exists($appPage)) {
            return $appPage;
        }

        // Sprawdź pages/index.tsx (Pages Router)
        $pagesIndex = "{$templatePath}/src/pages/index.tsx";
        if (file_exists($pagesIndex)) {
            return $pagesIndex;
        }

        $pagesIndex = "{$templatePath}/pages/index.tsx";
        if (file_exists($pagesIndex)) {
            return $pagesIndex;
        }

        return null;
    }
}
