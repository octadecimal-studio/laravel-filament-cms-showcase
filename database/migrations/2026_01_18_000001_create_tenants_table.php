<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja tworząca tabelę tenantów dla systemu multi-tenancy.
 */
return new class extends Migration
{
    /**
     * Uruchom migrację.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Podstawowe dane
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();

            // Plan i konfiguracja bazy
            $table->enum('plan', ['starter', 'pro', 'enterprise'])->default('starter');
            $table->enum('database_type', ['shared', 'dedicated'])->default('shared');
            $table->string('database_name')->nullable();

            // Ustawienia i status
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);

            // Timestampy
            $table->timestamps();
            $table->softDeletes();

            // Indeksy
            $table->index('is_active');
            $table->index('plan');
            $table->index(['is_active', 'plan']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
