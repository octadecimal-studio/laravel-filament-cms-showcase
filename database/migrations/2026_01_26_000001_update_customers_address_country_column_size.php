<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja zwiększająca rozmiar kolumny address_country w tabeli customers
 * z 2 znaków (kod ISO) na 100 znaków (pełna nazwa kraju).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('address_country', 100)->default('Polska')->change();
            $table->string('billing_address_country', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('address_country', 2)->default('PL')->change();
            $table->string('billing_address_country', 2)->nullable()->change();
        });
    }
};
