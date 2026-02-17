<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Content\Services\StrapiService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Komenda do podłączenia strony pod Strapi CMS i wygenerowania przykładowego kontentu.
 */
class ConnectStrapiCMS extends Command
{
    /**
     * Sygnatura komendy.
     *
     * @var string
     */
    protected $signature = 'strapi:connect 
                            {schema_path : Ścieżka do pliku schema.json}
                            {--instance=motorent : Instancja Strapi (sites, motorent)}
                            {--url= : URL do Strapi API (nadpisuje config)}
                            {--token= : Token API (nadpisuje config)}';

    /**
     * Opis komendy.
     *
     * @var string
     */
    protected $description = 'Podłącza stronę pod Strapi CMS i generuje przykładowy kontent na podstawie schema.json';

    /**
     * Wykonaj komendę.
     */
    public function handle(): int
    {
        $schemaPath = $this->argument('schema_path');
        $instance = $this->option('instance');

        $this->info("🚀 Podłączanie strony pod Strapi CMS (instancja: {$instance})...");

        // 1. Wczytaj schema.json
        if (! file_exists($schemaPath)) {
            $this->error("❌ Plik schema.json nie istnieje: {$schemaPath}");

            return Command::FAILURE;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        if (! $schema) {
            $this->error('❌ Nie udało się sparsować schema.json');

            return Command::FAILURE;
        }

        $this->info("✅ Wczytano schema.json ({$schemaPath})");

        // 2. Konfiguracja Strapi
        $strapiUrl = $this->option('url') ?? config("strapi.instances.{$instance}.api_url", config('strapi.api_url'));
        $strapiToken = $this->option('token') ?? config("strapi.instances.{$instance}.api_token", config('strapi.api_token'));

        $strapiService = new StrapiService($strapiUrl, $strapiToken);

        // 3. Health check
        $this->info("🏥 Sprawdzanie połączenia ze Strapi ({$strapiUrl})...");
        if (! $strapiService->healthCheck()) {
            $this->warn('⚠️  Strapi nie odpowiada, ale kontynuujemy...');
        } else {
            $this->info('✅ Połączenie ze Strapi działa');
        }

        // 4. Generuj przykładowy kontent
        $this->info('📝 Generowanie przykładowego kontentu...');
        $this->generateExampleContent($schema, $strapiService);

        $this->newLine();
        $this->info('✅ Przykładowy kontent został wygenerowany!');
        $this->info("📊 Sprawdź w Strapi: {$strapiUrl}/admin");

        return Command::SUCCESS;
    }

    /**
     * Generuje przykładowy kontent na podstawie schematu.
     *
     * @param  array<string, mixed>  $schema
     */
    private function generateExampleContent(array $schema, StrapiService $strapiService): void
    {
        // Single Types (site-setting, navigation, footer)
        if (isset($schema['singleTypes'])) {
            foreach ($schema['singleTypes'] as $typeKey => $typeDef) {
                $singularName = $typeDef['info']['singularName'] ?? null;
                if (! $singularName) {
                    continue;
                }

                $this->info("  📄 Generowanie {$singularName}...");
                $exampleData = $this->generateExampleDataForType($typeDef);
                    try {
                        $result = $strapiService->createEntry($singularName, $exampleData);

                        if ($result) {
                            $this->info("    ✅ Utworzono {$singularName}");
                        } else {
                            $this->warn("    ⚠️  Nie udało się utworzyć {$singularName} (może już istnieć)");
                        }
                    } catch (\RuntimeException $e) {
                        $this->error("    ❌ {$e->getMessage()}");
                    } catch (\Exception $e) {
                        $this->warn("    ⚠️  Błąd: {$e->getMessage()}");
                    }
            }
        }

        // Collection Types
        if (isset($schema['contentTypes'])) {
            foreach ($schema['contentTypes'] as $typeKey => $typeDef) {
                // Pomiń single types (już przetworzone)
                if (($typeDef['kind'] ?? null) === 'singleType') {
                    continue;
                }

                $singularName = $typeDef['info']['singularName'] ?? null;
                if (! $singularName) {
                    continue;
                }

                $this->info("  📦 Generowanie {$singularName}...");

                // Generuj 2-3 przykładowe wpisy dla collection types
                $count = $this->getExampleCountForType($singularName);
                for ($i = 0; $i < $count; $i++) {
                    $exampleData = $this->generateExampleDataForType($typeDef, $i);
                    try {
                        $result = $strapiService->createEntry($singularName, $exampleData);

                        if ($result) {
                            // Publikuj jeśli ma draftAndPublish
                            if ($typeDef['options']['draftAndPublish'] ?? false) {
                                $strapiService->publishEntry($singularName, $result['id']);
                            }
                            $this->info("    ✅ Utworzono {$singularName} #{$i}");
                        } else {
                            $this->warn("    ⚠️  Nie udało się utworzyć {$singularName} #{$i} (może już istnieć)");
                        }
                    } catch (\RuntimeException $e) {
                        $this->error("    ❌ {$e->getMessage()}");
                        break; // Przerwij pętlę jeśli Content Type nie istnieje
                    } catch (\Exception $e) {
                        $this->warn("    ⚠️  Błąd: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    /**
     * Generuje przykładowe dane dla typu.
     *
     * @param  array<string, mixed>  $typeDef
     * @param  int  $index Indeks dla różnorodności danych
     * @return array<string, mixed>
     */
    private function generateExampleDataForType(array $typeDef, int $index = 0): array
    {
        $data = [];
        $attributes = $typeDef['attributes'] ?? [];
        $singularName = $typeDef['info']['singularName'] ?? '';

        foreach ($attributes as $fieldName => $fieldDef) {
            // Pomiń relacje (będą dodane później)
            if (($fieldDef['type'] ?? null) === 'relation') {
                continue;
            }

            // Pomiń komponenty (będą dodane później)
            if (($fieldDef['type'] ?? null) === 'component') {
                continue;
            }

            $value = $this->generateExampleValue($fieldName, $fieldDef, $singularName, $index);
            if ($value !== null) {
                $data[$fieldName] = $value;
            }
        }

        return $data;
    }

    /**
     * Generuje przykładową wartość dla pola.
     *
     * @param  string  $fieldName
     * @param  array<string, mixed>  $fieldDef
     * @param  string  $contentType
     * @param  int  $index
     * @return mixed
     */
    private function generateExampleValue(string $fieldName, array $fieldDef, string $contentType, int $index): mixed
    {
        $type = $fieldDef['type'] ?? null;
        $required = $fieldDef['required'] ?? false;
        $default = $fieldDef['default'] ?? null;

        // Jeśli jest default, użyj go
        if ($default !== null) {
            return $default;
        }

        // Jeśli nie jest wymagane i nie ma default, zwróć null
        if (! $required && $type !== 'boolean') {
            return null;
        }

        // Generuj wartość na podstawie typu
        return match ($type) {
            'string' => $this->generateStringValue($fieldName, $contentType, $index, $fieldDef),
            'text' => $this->generateTextValue($fieldName, $contentType, $index),
            'richtext' => $this->generateRichTextValue($fieldName, $contentType, $index),
            'email' => "example{$index}@example.com",
            'integer' => $this->generateIntegerValue($fieldName, $fieldDef, $index),
            'decimal' => $this->generateDecimalValue($fieldName, $fieldDef, $index),
            'boolean' => $fieldDef['default'] ?? ($index % 2 === 0),
            'date' => now()->addDays($index)->format('Y-m-d'),
            'datetime' => now()->addDays($index)->toIso8601String(),
            'time' => now()->format('H:i:s'),
            'json' => $this->generateJsonValue($fieldName, $contentType, $index),
            'uid' => Str::slug($this->generateStringValue($fieldName, $contentType, $index, $fieldDef)),
            'media' => null, // Media będzie wymagało uploadu
            default => null,
        };
    }

    /**
     * Generuje wartość string.
     */
    private function generateStringValue(string $fieldName, string $contentType, int $index, array $fieldDef): string
    {
        $examples = [
            'siteTitle' => ['MotoRent Demo', 'Wypożyczalnia Motocykli', 'MotoRent'],
            'name' => ['Yamaha Tracer 900 GT', 'Kawasaki Z650', 'Honda CB650R', 'Ducati Monster 821'],
            'title' => ['Profesjonalna Wypożyczalnia', 'Najlepsze Motocykle', 'Doświadczenie i Jakość'],
            'description' => ['Opis produktu', 'Szczegółowy opis', 'Kompletny opis'],
            'slug' => ['yamaha-tracer-900', 'kawasaki-z650', 'honda-cb650r'],
            'contactPhone' => ['+48 123 456 789', '+48 987 654 321'],
            'address' => ['ul. Przykładowa 123, 00-000 Warszawa', 'ul. Testowa 456, 30-000 Kraków'],
            'openingHours' => ['Pon-Pt: 9:00-18:00, Sob: 10:00-16:00', 'Pon-Nie: 8:00-20:00'],
            'mapCoordinates' => ['52.2297,21.0122', '50.0647,19.9450'],
        ];

        $key = $fieldName;
        if (isset($examples[$key])) {
            return $examples[$key][$index % count($examples[$key])] ?? $examples[$key][0];
        }

        // Fallback: generuj na podstawie nazwy pola
        return ucfirst(str_replace('_', ' ', Str::snake($fieldName))).' '.($index + 1);
    }

    /**
     * Generuje wartość text.
     */
    private function generateTextValue(string $fieldName, string $contentType, int $index): string
    {
        $examples = [
            'description' => [
                'To jest szczegółowy opis produktu lub usługi. Zawiera wszystkie ważne informacje dla klientów.',
                'Kompletny opis zawierający wszystkie szczegóły i informacje potrzebne do podjęcia decyzji.',
                'Szczegółowy opis z dodatkowymi informacjami i szczegółami technicznymi.',
            ],
        ];

        if (isset($examples[$fieldName])) {
            return $examples[$fieldName][$index % count($examples[$fieldName])] ?? $examples[$fieldName][0];
        }

        return "Przykładowy tekst dla pola {$fieldName} (wpis #{$index})";
    }

    /**
     * Generuje wartość richtext.
     */
    private function generateRichTextValue(string $fieldName, string $contentType, int $index): string
    {
        return "<p>To jest przykładowa treść w formacie <strong>Rich Text</strong> dla pola <em>{$fieldName}</em>.</p><p>Może zawierać <a href='#'>linki</a> i formatowanie.</p>";
    }

    /**
     * Generuje wartość integer.
     */
    private function generateIntegerValue(string $fieldName, array $fieldDef, int $index): int
    {
        $min = $fieldDef['min'] ?? 0;
        $max = $fieldDef['max'] ?? 100;

        $examples = [
            'engineCapacity' => [125, 300, 650, 900, 1200],
            'year' => [2020, 2021, 2022, 2023, 2024],
            'stepNumber' => [1, 2, 3, 4, 5],
            'rating' => [4, 5, 4, 5, 5],
            'order' => [0, 1, 2, 3, 4],
        ];

        if (isset($examples[$fieldName])) {
            return $examples[$fieldName][$index % count($examples[$fieldName])] ?? $examples[$fieldName][0];
        }

        return min($max, max($min, $min + $index));
    }

    /**
     * Generuje wartość decimal.
     */
    private function generateDecimalValue(string $fieldName, array $fieldDef, int $index): float
    {
        $min = (float) ($fieldDef['min'] ?? 0);

        $examples = [
            'pricePerDay' => [150.00, 200.00, 250.00, 300.00, 350.00],
            'pricePerWeek' => [900.00, 1200.00, 1500.00, 1800.00, 2100.00],
            'pricePerMonth' => [3500.00, 4500.00, 5500.00, 6500.00, 7500.00],
            'deposit' => [1000.00, 1500.00, 2000.00, 2500.00, 3000.00],
        ];

        if (isset($examples[$fieldName])) {
            return $examples[$fieldName][$index % count($examples[$fieldName])] ?? $examples[$fieldName][0];
        }

        return round($min + ($index * 10.5), 2);
    }

    /**
     * Generuje wartość JSON.
     */
    private function generateJsonValue(string $fieldName, string $contentType, int $index): array
    {
        if ($fieldName === 'specifications') {
            return [
                'engine' => '4-cylindrowy, 900cc',
                'power' => '95 KM',
                'torque' => '93 Nm',
                'weight' => '215 kg',
                'fuelCapacity' => '18 L',
            ];
        }

        if ($fieldName === 'categoryPrices') {
            return [
                'sport' => 250.00,
                'cruiser' => 200.00,
                'touring' => 300.00,
            ];
        }

        return ['key' => 'value', 'index' => $index];
    }

    /**
     * Zwraca liczbę przykładowych wpisów dla typu.
     */
    private function getExampleCountForType(string $singularName): int
    {
        return match ($singularName) {
            'motorcycle' => 5,
            'motorcycle-category' => 4,
            'motorcycle-brand' => 5,
            'feature' => 6,
            'process-step' => 5,
            'testimonial' => 8,
            'faq-item' => 10,
            'blog-post' => 3,
            default => 3,
        };
    }
}

