<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder tworzący przykładowego tenanta i użytkowników.
 */
class TenantSeeder extends Seeder
{
    /**
     * Uruchom seeder.
     */
    public function run(): void
    {
        // === SYSTEM TENANT (dla super adminów) ===
        $systemTenant = Tenant::firstOrCreate(
            ['slug' => 'system'],
            [
                'id' => '00000000-0000-0000-0000-000000000000',
                'name' => 'System (Super Admini)',
                'domain' => null,
                'plan' => 'enterprise',
                'database_type' => 'shared',
                'settings' => [
                    'locale' => 'pl',
                    'timezone' => 'Europe/Warsaw',
                    'is_system' => true,
                ],
                'is_active' => true,
            ]
        );

        // === SUPER ADMIN (przypisany do system tenant) ===
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@octadecimal.studio'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        // Pola chronione ustawiamy bezpośrednio (nie przez mass assignment)
        $superAdmin->is_super_admin = true;
        $superAdmin->tenant_id = $systemTenant->id;
        $superAdmin->save();
        $superAdmin->assignRole('super_admin');

        // === PRZYKŁADOWY TENANT ===
        $demoTenant = Tenant::firstOrCreate(
            ['slug' => 'demo-studio'],
            [
                'name' => 'Demo Studio',
                'domain' => null,
                'plan' => 'pro',
                'database_type' => 'shared',
                'settings' => [
                    'locale' => 'pl',
                    'timezone' => 'Europe/Warsaw',
                    'branding' => [
                        'primary_color' => '#3B82F6',
                        'logo' => null,
                    ],
                ],
                'is_active' => true,
            ]
        );

        // Tenant Admin dla demo tenanta
        $tenantAdmin = User::firstOrCreate(
            ['email' => 'admin@demo-studio.local'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        // tenant_id ustawiamy bezpośrednio (chronione przed mass assignment)
        $tenantAdmin->tenant_id = $demoTenant->id;
        $tenantAdmin->save();
        $tenantAdmin->assignRole('tenant_admin');

        // Editor dla demo tenanta
        $editor = User::firstOrCreate(
            ['email' => 'editor@demo-studio.local'],
            [
                'name' => 'Demo Editor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $editor->tenant_id = $demoTenant->id;
        $editor->save();
        $editor->assignRole('editor');

        // Viewer dla demo tenanta
        $viewer = User::firstOrCreate(
            ['email' => 'viewer@demo-studio.local'],
            [
                'name' => 'Demo Viewer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $viewer->tenant_id = $demoTenant->id;
        $viewer->save();
        $viewer->assignRole('viewer');

        // === DRUGI TENANT (dla testów izolacji) ===
        $secondTenant = Tenant::firstOrCreate(
            ['slug' => 'test-agency'],
            [
                'name' => 'Test Agency',
                'domain' => null,
                'plan' => 'starter',
                'database_type' => 'shared',
                'settings' => [
                    'locale' => 'pl',
                    'timezone' => 'Europe/Warsaw',
                ],
                'is_active' => true,
            ]
        );

        $secondAdmin = User::firstOrCreate(
            ['email' => 'admin@test-agency.local'],
            [
                'name' => 'Test Agency Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $secondAdmin->tenant_id = $secondTenant->id;
        $secondAdmin->save();
        $secondAdmin->assignRole('tenant_admin');
    }
}
