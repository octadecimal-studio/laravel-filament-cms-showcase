<?php

declare(strict_types=1);

namespace App\Pricing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Octadecimal\Rental\Contracts\PricingStrategyInterface;
use Octadecimal\Rental\Models\RentalType;

/**
 * Strategia cenowa "per rentable" zwracajaca total_amount w GROSZACH.
 *
 * Pakiet octadecimalhq/reservation-system w PerRentablePricingStrategy
 * traktuje pole `price_per_day` jako wartosc juz w jednostce mniejszej (groszach),
 * co nie pasuje do schematu `two_wheels_motorcycles.price_per_day` (decimal,
 * wartosci w PLN, np. 600.00 = 600 zl/dzien).
 *
 * Przelewy24Service wymaga total_amount w groszach. Bez konwersji platnosc
 * pokazywala 30 PLN zamiast 3000 PLN (cena 600 PLN * 5 dni = 3000 -> P24 czyta
 * jako 3000 groszy = 30 PLN).
 *
 * Algorytm identyczny z PerRentablePricingStrategy + konwersja PLN->grosze (*100).
 */
class PerRentablePLNStrategy implements PricingStrategyInterface
{
    public function calculate(
        Model $rentable,
        ?RentalType $rentalType,
        int $qty,
        string $startDate,
        string $endDate,
        array $payload = []
    ): int {
        $meta = (array) ($rentalType?->meta ?? []);
        $unit = $meta['unit'] ?? config('rental.pricing_default_unit', 'day');
        $field = $meta['rentable_field'] ?? config('rental.pricing_default_rentable_field', 'price_per_day');

        $unitPricePLN = (float) ($rentable->{$field} ?? 0);
        if ($unitPricePLN <= 0) {
            return 0;
        }

        $units = $this->countUnits($startDate, $endDate, (string) $unit);
        $qty = max(1, $qty);

        // PLN -> grosze: zaokraglenie po pomnozeniu (precision-safe)
        return (int) round($unitPricePLN * 100) * $units * $qty;
    }

    private function countUnits(string $start, string $end, string $unit): int
    {
        $startCarbon = Carbon::parse($start);
        $endCarbon = Carbon::parse($end);

        return match ($unit) {
            'week' => max(1, (int) ceil($startCarbon->diffInDays($endCarbon) / 7)),
            'month' => max(1, (int) ceil($startCarbon->diffInMonths($endCarbon))),
            default => max(1, (int) ceil($startCarbon->diffInDays($endCarbon))),
        };
    }
}
