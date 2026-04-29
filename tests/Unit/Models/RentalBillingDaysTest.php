<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Octadecimal\Rental\Models\Rental;
use Tests\TestCase;

/**
 * KML-0047: liczba dob rozliczeniowych (algorytm hour-aware).
 *
 * Test sprawdza algorytm computeBillingDays() na poziomie modelu (bez DB —
 * uzywa setRawAttributes + cast na Carbon).
 *
 * Tabela kanoniczna z business spec L0:
 *
 *   Odbior              Zwrot               Diff h   Doby
 *   2026-05-01 10:00    2026-05-02 10:00    24       1
 *   2026-05-01 10:00    2026-05-02 10:01    24.02    2
 *   2026-05-01 10:00    2026-05-02 09:59    23.98    1
 *   2026-05-01 10:00    2026-05-08 10:00    168      7
 *   2026-05-01 10:00    2026-05-08 11:00    169      8
 *   2026-05-01 10:00    2026-06-01 10:00    744      31
 *   2026-05-01 10:00    2026-05-01 10:00    0        1
 *   2026-05-01 10:00    2026-05-01 10:30    0.5      1
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

    public function test_one_minute_past_24h_rounds_up_to_two_days(): void
    {
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-02 10:01:00');
        $this->assertSame(2, $rental->computeBillingDays());
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

    public function test_one_week_plus_one_hour_rounds_up_to_eight(): void
    {
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-08 11:00:00');
        $this->assertSame(8, $rental->computeBillingDays());
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

    public function test_half_hour_rounds_up_to_one_day(): void
    {
        $rental = $this->makeRental('2026-05-01 10:00:00', '2026-05-01 10:30:00');
        $this->assertSame(1, $rental->computeBillingDays());
    }
}
