<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja tworząca tabelę dostępów do funkcjonalności dla tenantów.
 *
 * Tabela przechowuje granularne uprawnienia (CRUD) dla każdej funkcjonalności
 * przypisanej do tenanta. Umożliwia elastyczne zarządzanie dostępami klientów
 * do poszczególnych modułów systemu.
 */
return new class extends Migration
{
    /**
     * Uruchom migrację.
     */
    public function up(): void
    {
        Schema::create('tenant_feature_access', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            
            // Nazwa funkcjonalności (np. motorcycles, reservations, brands)
            $table->string('feature', 100);
            
            // Grupa funkcjonalności dla UI (np. motorent_demo, plugins)
            $table->string('feature_group', 100)->nullable();
            
            // Uprawnienia CRUD
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            
            $table->timestamps();
            
            // Klucz obcy do tabeli tenants
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            
            // Unikalność: jeden tenant może mieć tylko jeden wpis dla danej funkcjonalności
            $table->unique(['tenant_id', 'feature'], 'tenant_feature_unique');
            
            // Indeks dla szybkiego wyszukiwania po tenancie
            $table->index('tenant_id', 'tenant_feature_tenant_idx');
        });
    }

    /**
     * Wycofaj migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_access');
    }
};
