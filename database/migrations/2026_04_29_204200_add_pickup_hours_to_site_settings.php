<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KML-0047: konfigurowalna lista godzin odbioru/zwrotu motocykla.
 *
 * Lista godzin (HH:MM) dostepnych w TimePicker na froncie wizard rezerwacji
 * oraz w Filament admin (DateTimePicker minutesStep). Domyslnie: 09:00..17:00 co godzine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_wheels_site_settings', function (Blueprint $t) {
            $t->json('pickup_hours')->nullable()->after('opening_hours');
        });

        // Default lista godzin 09:00..17:00 dla istniejacych rekordow
        \DB::table('two_wheels_site_settings')
            ->whereNull('pickup_hours')
            ->update([
                'pickup_hours' => json_encode(['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00']),
            ]);
    }

    public function down(): void
    {
        Schema::table('two_wheels_site_settings', function (Blueprint $t) {
            $t->dropColumn('pickup_hours');
        });
    }
};
