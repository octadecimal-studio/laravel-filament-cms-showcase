<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plugin;
use Illuminate\Database\Seeder;

class PluginSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $plugins = [
            [
                'name' => 'Filament Shield',
                'package' => 'bezhansalleh/filament-shield',
                'class_name' => 'BezhanSalleh\FilamentShield\FilamentShieldPlugin',
                'description' => 'Plugin do zarządzania rolami i uprawnieniami w Filament.',
                'version' => '^3.9',
                'author' => 'Bezhan Salleh',
                'homepage' => 'https://github.com/bezhanSalleh/filament-shield',
                'repository' => 'https://github.com/bezhanSalleh/filament-shield',
                'is_installed' => true,
                'is_enabled' => true,
                'is_official' => false,
                'category' => 'security',
                'tags' => ['security', 'roles', 'permissions', 'rbac'],
            ],
            [
                'name' => 'Palette Switcher',
                'package' => 'octopyid/filament-palette',
                'class_name' => 'Octopy\Filament\Palette\PaletteSwitcherPlugin',
                'description' => 'Plugin do przełączania palet kolorów w panelu Filament.',
                'version' => '^1.0',
                'author' => 'Supian M',
                'homepage' => 'https://filamentphp.com/plugins/supianidz-palette',
                'repository' => 'https://github.com/octopyid/filament-palette',
                'is_installed' => true,
                'is_enabled' => true,
                'is_official' => false,
                'category' => 'ui',
                'tags' => ['ui', 'colors', 'theme', 'palette'],
            ],
        ];

        foreach ($plugins as $pluginData) {
            Plugin::updateOrCreate(
                ['package' => $pluginData['package']],
                array_merge($pluginData, [
                    'installed_at' => now(),
                ])
            );
        }
    }
}
