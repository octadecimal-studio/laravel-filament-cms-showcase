<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Główny seeder bazy danych.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seeduje bazę danych aplikacji.
     */
    public function run(): void
    {
        // Najpierw role i uprawnienia
        $this->call(RolesAndPermissionsSeeder::class);

        // Następnie tenanty i użytkownicy (legacy)
        $this->call(TenantSeeder::class);

        // Nowy workflow CMS z przykładowymi danymi
        $this->call(CmsWorkflowSeeder::class);

        // Rezerwacje z produkcji (Plugin Reservations)
        $this->call(ReservationSeeder::class);

        // Konto klienta MotoRent Demo
        $this->call(TwoWheelsClientSeeder::class);

        // Treści MotoRent (motocykle, marki, kategorie, features, steps, testimonials)
        $this->call(TwoWheelsContentSeeder::class);
    }
}
