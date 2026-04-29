<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaje kolumne booking_mode do two_wheels_motorcycles.
 *
 * Tryby:
 *  - online (default) - rezerwacja przez Filament + Przelewy24
 *  - phone            - rezerwacja przez stary formularz e-mail (kontakt)
 *
 * Pozwala adminowi oznaczyc motocykl jako wymagajacy rezerwacji telefonicznej
 * (np. dla rzadkich modeli wymagajacych dodatkowych ustalen).
 *
 * @see KML-0047 (rozszerzenie: tryb rezerwacji per motocykl)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_wheels_motorcycles', function (Blueprint $t) {
            $t->enum('booking_mode', ['online', 'phone'])
                ->default('online')
                ->after('published')
                ->comment('Tryb rezerwacji: online (P24) lub phone (formularz e-mail)');
        });
    }

    public function down(): void
    {
        Schema::table('two_wheels_motorcycles', function (Blueprint $t) {
            $t->dropColumn('booking_mode');
        });
    }
};
