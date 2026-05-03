<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pole email w tabeli rentals — zmiana na nullable.
 *
 * Admin tworzący rezerwację ręcznie (CMS) może nie znać emaila klienta
 * (rezerwacja telefoniczna, walk-in). Oryginalny schemat pakietu
 * octadecimalhq/reservation-system miał email NOT NULL, co powodowało
 * SQLSTATE[23000] przy zapisie bez emaila przez Filament.
 *
 * Zmiana zastosowana bezpośrednio ALTER TABLE na PRD 2026-05-03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
