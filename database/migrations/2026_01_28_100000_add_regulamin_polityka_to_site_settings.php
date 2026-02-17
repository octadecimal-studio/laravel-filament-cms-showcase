<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaje pola regulamin_content i polityka_prywatnosci_content do ustawień strony MotoRent.
 * Edycja w CMS (Ustawienia strony), sekcje na stronie głównej.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_wheels_site_settings', function (Blueprint $table) {
            $table->longText('regulamin_content')->nullable()->after('about_us_content');
            $table->longText('polityka_prywatnosci_content')->nullable()->after('regulamin_content');
        });
    }

    public function down(): void
    {
        Schema::table('two_wheels_site_settings', function (Blueprint $table) {
            $table->dropColumn(['regulamin_content', 'polityka_prywatnosci_content']);
        });
    }
};
