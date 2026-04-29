<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Octadecimal\Rental\Models\Rental;
use Tests\TestCase;

/**
 * KML-0047 (final): liczba dob rozliczeniowych — kwadratowo, bez zaokraglen.
 *
 * Frontend wymusza ta sama godzine odbioru i zwrotu, wiec roznica jest zawsze
 * wielokrotnoscia 24h. Filament admin moze ustawic rozne godziny -> wtedy floor.
 *
 * Tabela kanoniczna (final L0):
 *
 *   Odbior              Zwrot               Diff h   Doby   Komentarz
 *   2026-05-01 10:00    2026-05-02 10:00    24       1      pelna doba
 *   2026-05-01 10:00    2026-05-02 10:01    24.02    1      floor (frontend nie pozwala)
 *   2026-05-01 10:00    2026-05-02 09:59    23.98    1      min 1 (floor=0)
 *   2026-05-01 10:00    2026-05-08 10:00    168      7
 *   2026-05-01 10:00    2026-05-08 11:00    169      7      floor (frontend nie pozwala)
 *   2026-05-01 10:00    2026-06-01 10:00    744      31
 *   2026-05-01 10:00    2026-05-01 10:00    0        1      min 1
 *   2026-05-01 10:00    2026-05-01 10:30    0.5      1      min 1
 */
class RentalBillingDaysTest extends TestCase
{
    private function makeRental(string $startAt, string $endAt): Rental
    {
        $rental = new Rental;
        $rental->start_at = $startAt;
        $rental->end_at = $endAt;

        return $rental;
    }

    public function test_full_day_24h_equals_one_day(): void
    {
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-02 10:00:00');
        $this->assertSame(1, $rental->computeBillingDays());
    }

    public function test_one_minute_past_24h_floors_to_one_day(): void
    {
        // KML-0047 final: kwadratowo, brak zaokraglen w gore
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-02 10:01:00');
        $this->assertSame(1, $rental->computeBillingDays());
    }

    public function test_just_under_24h_is_one_day(): void
    {
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-02 09:59:00');
        $this->assertSame(1, $rental->computeBillingDays());
    }

    public function test_seven_full_days(): void
    {
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-08 10:00:00');
        $this->assertSame(7, $rental->computeBillingDays());
    }

    public function test_one_week_plus_one_hour_floors_to_seven(): void
    {
        // KML-0047 final: kwadratowo, brak zaokraglen w gore
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-08 11:00:00');
        $this->assertSame(7, $rental->computeBillingDays());
    }

    public function test_one_month_31_days(): void
    {
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-06-01 10:00:00');
        $this->assertSame(31, $rental->computeBillingDays());
    }

    public function test_zero_duration_returns_one_day_minimum(): void
    {
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-01 10:00:00');
        $this->assertSame(1, $rental->computeBillingDays());
    }

    public function test_half_hour_minimum_one_day(): void
    {
        // KML-0047 final: floor(0.5h/24)=0 -> min 1
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-01 10:30:00');
        $this->assertSame(1, $rental->computeBillingDays());
    }
}
