<?php

declare(strict_types=1);

namespace App\Plugins\Reservations;

use App\Plugins\Core\AbstractPlugin;
use App\Plugins\Reservations\Filament\Resources\ReservationResource;

/**
 * Plugin rezerwacji dla stron z wypożyczalnią.
 *
 * Funkcjonalności:
 * - Formularz rezerwacji na stronie (API endpoint)
 * - Panel zarządzania rezerwacjami w Filament
 * - Statusy: pending, confirmed, cancelled, completed
 * - RODO consent tracking
 *
 * Pierwszy plugin stworzony dla example-rental.test
 */
class ReservationsPlugin extends AbstractPlugin
{
    /**
     * {@inheritdoc}
     */
    public static function slug(): string
    {
        return 'reservations';
    }

    /**
     * {@inheritdoc}
     */
    public static function name(): string
    {
        return 'Rezerwacje';
    }

    /**
     * {@inheritdoc}
     */
    public static function description(): string
    {
        return 'System rezerwacji dla stron z wypożyczalnią. Formularz kontaktowy z wyborem produktu, statusy rezerwacji, RODO compliance.';
    }

    /**
     * {@inheritdoc}
     */
    public static function version(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritdoc}
     */
    public static function icon(): string
    {
        return 'heroicon-o-calendar-days';
    }

    /**
     * {@inheritdoc}
     */
    public function filamentResources(): array
    {
        return [
            ReservationResource::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfig(): array
    {
        return [
            // Email do powiadomień o nowych rezerwacjach
            'notification_email' => null,

            // Czy wysyłać potwierdzenie do klienta
            'send_customer_confirmation' => true,

            // Domyślny status nowej rezerwacji
            'default_status' => 'pending',

            // Czy wymagana zgoda RODO
            'require_rodo_consent' => true,

            // Minimalna liczba dni wyprzedzenia
            'min_advance_days' => 1,

            // Maksymalna liczba dni wyprzedzenia
            'max_advance_days' => 365,
        ];
    }
}
