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
 * - super_admin: Pełny dostęp (Octadecimal Studio)
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

        // CRM - Klienci
        $customerPermissions = [
            'customers.view_any',
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
        ];

        // CRM - Strony
        $sitePermissions = [
            'sites.view_any',
            'sites.view',
            'sites.create',
            'sites.update',
            'sites.delete',
            'sites.deploy',
        ];

        // CRM - Zlecenia
        $orderPermissions = [
            'orders.view_any',
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
        ];

        // CRM - Poprawki
        $correctionPermissions = [
            'corrections.view_any',
            'corrections.view',
            'corrections.create',
            'corrections.update',
            'corrections.delete',
        ];

        // Lead Generation - Ogłoszenia
        $listingPermissions = [
            'listings.view_any',
            'listings.view',
            'listings.create',
            'listings.update',
            'listings.delete',
        ];

        // Finanse - Faktury
        $invoicePermissions = [
            'invoices.view_any',
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
        ];

        // DevOps - Deploymenty
        $deploymentPermissions = [
            'deployments.view_any',
            'deployments.view',
        ];

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
            $customerPermissions,
            $sitePermissions,
            $orderPermissions,
            $correctionPermissions,
            $listingPermissions,
            $invoicePermissions,
            $deploymentPermissions,
            $reservationPermissions,
            $userPermissions,
            $settingsPermissions
        );

        // Utwórz wszystkie uprawnienia
        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // === ROLE ===

        // Super Admin - pełny dostęp do wszystkiego (Octadecimal Studio)
        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions($allPermissions);

        // Tenant Admin - pełny dostęp w ramach swojego tenanta
        $tenantAdmin = Role::findOrCreate('tenant_admin', 'web');
        $tenantAdmin->syncPermissions($allPermissions);

        // Client - klient z dostępem do swoich stron i rezerwacji
        // NIE widzi: innych klientów, ogłoszeń, faktur, deploymentów, userów
        $client = Role::findOrCreate('client', 'web');
        $client->syncPermissions([
            // Strony - tylko view
            'sites.view_any',
            'sites.view',

            // Rezerwacje - pełny dostęp
            ...$reservationPermissions,

            // Poprawki - może zgłaszać i przeglądać
            'corrections.view_any',
            'corrections.view',
            'corrections.create',
        ]);

        // Editor - może edytować treści
        $editor = Role::findOrCreate('editor', 'web');
        $editor->syncPermissions([
            'sites.view_any',
            'sites.view',
            ...$reservationPermissions,
            'corrections.view_any',
            'corrections.view',
        ]);

        // Viewer - tylko odczyt
        $viewer = Role::findOrCreate('viewer', 'web');
        $viewer->syncPermissions([
            'sites.view_any',
            'sites.view',
            'reservations.view_any',
            'reservations.view',
        ]);
    }
}
