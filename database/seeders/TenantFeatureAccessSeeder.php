<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\TenantFeatureAccess;
use Illuminate\Database\Seeder;

/**
 * Seeder dla domyślnych dostępów tenantów do funkcjonalności.
 *
 * Tworzy pełne dostępy dla tenanta demo-studio (example-rental.test).
 */
class TenantFeatureAccessSeeder extends Seeder
{
    /**
     * Uruchom seeder.
     */
    public function run(): void
    {
        // Pobierz tenant demo-studio
        $demoStudio = Tenant::where('slug', 'demo-studio')->first();

        if (!$demoStudio) {
            $this->command->warn('Tenant demo-studio nie istnieje. Pomijam seeder.');
            return;
        }

        $this->command->info("Tworzę dostępy dla tenanta: {$demoStudio->name}");

        // Pełne dostępy dla MotoRent Demo
        $fullAccessFeatures = [
            'motorcycles',
            'motorcycle_brands',
            'motorcycle_categories',
            'testimonials',
            'features',
            'process_steps',
            'site_settings',
            'gallery',
            'reservations',
        ];

        foreach ($fullAccessFeatures as $feature) {
            TenantFeatureAccess::setAccess($demoStudio->id, $feature, [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true,
            ]);
            $this->command->info("  ✓ {$feature} - pełny dostęp");
        }

        // Dostęp tylko do odczytu dla CRM
        $readOnlyFeatures = [
            'sites',
            'corrections',
        ];

        foreach ($readOnlyFeatures as $feature) {
            TenantFeatureAccess::setAccess($demoStudio->id, $feature, [
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
            ]);
            $this->command->info("  ✓ {$feature} - tylko odczyt");
        }

        $this->command->info('Seeder zakończony pomyślnie.');
    }
}
