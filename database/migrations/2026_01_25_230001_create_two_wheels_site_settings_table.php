<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja: tabela ustawień strony dla MotoRent Demo.
 *
 * Single type - jedna konfiguracja per tenant.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('two_wheels_site_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('site_title');
            $table->text('site_description')->nullable();
            $table->uuid('logo_id')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('address')->nullable();
            $table->string('opening_hours')->nullable();
            $table->string('map_coordinates')->nullable();
            $table->timestamps();

            // Indeksy
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('logo_id')
                ->references('id')
                ->on('media')
                ->onDelete('set null');

            // Tylko jedno ustawienie per tenant
            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_wheels_site_settings');
    }
};
