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

        // Tenanty i użytkownicy
        $this->call(TenantSeeder::class);

        // Konto klienta MotoRent Demo (creates Site needed by ReservationSeeder)
        $this->call(TwoWheelsClientSeeder::class);

        // Rezerwacje z produkcji (Plugin Reservations)
        $this->call(ReservationSeeder::class);

        // Treści MotoRent (motocykle, marki, kategorie, features, steps, testimonials)
        $this->call(TwoWheelsContentSeeder::class);
    }
}
