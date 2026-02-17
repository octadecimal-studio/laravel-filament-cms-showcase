<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaje pole about_us_content do ustawień strony MotoRent.
 * Pozwala na edycję sekcji "O nas" przez admina i klienta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_wheels_site_settings', function (Blueprint $table) {
            $table->longText('about_us_content')->nullable()->after('site_description');
        });
    }

    public function down(): void
    {
        Schema::table('two_wheels_site_settings', function (Blueprint $table) {
            $table->dropColumn('about_us_content');
        });
    }
};
