<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Plugins\PluginResource\Pages;

use App\Filament\Resources\Modules\Plugins\PluginResource;
use App\Modules\Plugins\Services\PluginService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePlugin extends CreateRecord
{
    protected static string $resource = PluginResource::class;

    public function mount(): void
    {
        parent::mount();

        // Sprawdź czy są dane z wyszukiwania w URL
        $package = request()->query('package');
        if ($package) {
            $service = app(PluginService::class);
            $details = $service->getPackageDetails($package);
            
            if ($details) {
                // Spróbuj wykryć klasę pluginu
                $detectedClass = $service->detectPluginClass($package);
                
                $this->form->fill([
                    'name' => $details['name'] ?? '',
                    'package' => $package,
                    'class_name' => $detectedClass,
                    'description' => $details['description'] ?? '',
                    'version' => $details['versions'][$details['default_branch'] ?? 'dev-master']['version'] ?? null,
                    'author' => $details['maintainers'][0]['name'] ?? null,
                    'homepage' => $details['repository'] ?? null,
                    'repository' => $details['repository'] ?? null,
                    'category' => $this->guessCategory($details),
                    'tags' => $this->extractTags($details),
                ]);
            } else {
                // Jeśli nie ma szczegółów, wypełnij podstawowe dane i spróbuj wykryć klasę
                $detectedClass = $service->detectPluginClass($package);
                
                $this->form->fill([
                    'package' => $package,
                    'name' => $this->formatPackageName($package),
                    'class_name' => $detectedClass,
                ]);
            }
        }
    }

    /**
     * Formatuje nazwę pakietu na czytelną nazwę.
     */
    private function formatPackageName(string $package): string
    {
        $parts = explode('/', $package);
        $name = end($parts);
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Próbuje odgadnąć kategorię na podstawie danych pakietu.
     */
    private function guessCategory(array $details): ?string
    {
        $name = strtolower($details['name'] ?? '');
        $description = strtolower($details['description'] ?? '');

        if (str_contains($name, 'shield') || str_contains($description, 'permission') || str_contains($description, 'role')) {
            return 'security';
        }
        if (str_contains($name, 'media') || str_contains($description, 'media') || str_contains($description, 'upload')) {
            return 'content';
        }
        if (str_contains($name, 'palette') || str_contains($name, 'theme') || str_contains($description, 'color') || str_contains($description, 'ui')) {
            return 'ui';
        }
        if (str_contains($description, 'integration') || str_contains($description, 'api')) {
            return 'integration';
        }

        return 'other';
    }

    /**
     * Ekstraktuje tagi z danych pakietu.
     */
    private function extractTags(array $details): array
    {
        $tags = [];
        $keywords = $details['keywords'] ?? [];
        
        foreach ($keywords as $keyword) {
            if (strlen($keyword) < 20) { // Tylko krótkie tagi
                $tags[] = $keyword;
            }
        }

        return array_slice($tags, 0, 5); // Max 5 tagów
    }
}
