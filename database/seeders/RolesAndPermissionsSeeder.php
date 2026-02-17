<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder tworzący podstawowe role i uprawnienia systemu.
 *
 * ROLE:
 * - super_admin: Pełny dostęp
 * - tenant_admin: Admin w ramach tenanta
 * - client: Klient z dostępem do swoich stron (motorent)
 * - editor: Edytor treści
 * - viewer: Tylko odczyt
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Uruchom seeder.
     */
    public function run(): void
    {
        // Wyczyść cache uprawnień
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // === UPRAWNIENIA ===

        // Plugins - Rezerwacje
        $reservationPermissions = [
            'reservations.view_any',
            'reservations.view',
            'reservations.create',
            'reservations.update',
            'reservations.delete',
        ];

        // System - użytkownicy
        $userPermissions = [
            'users.view_any',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ];

        // System - ustawienia
        $settingsPermissions = [
            'settings.view',
            'settings.update',
        ];

        // Wszystkie uprawnienia
        $allPermissions = array_merge(
            $reservationPermissions,
            $userPermissions,
            $settingsPermissions
        );

        // Utwórz wszystkie uprawnienia
        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // === ROLE ===

        // Super Admin - pełny dostęp do wszystkiego
        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions($allPermissions);

        // Tenant Admin - pełny dostęp w ramach swojego tenanta
        $tenantAdmin = Role::findOrCreate('tenant_admin', 'web');
        $tenantAdmin->syncPermissions($allPermissions);

        // Client - klient z dostępem do rezerwacji
        $client = Role::findOrCreate('client', 'web');
        $client->syncPermissions([
            ...$reservationPermissions,
        ]);

        // Editor - może edytować treści
        $editor = Role::findOrCreate('editor', 'web');
        $editor->syncPermissions([
            ...$reservationPermissions,
        ]);

        // Viewer - tylko odczyt
        $viewer = Role::findOrCreate('viewer', 'web');
        $viewer->syncPermissions([
            'reservations.view_any',
            'reservations.view',
        ]);
    }
}
