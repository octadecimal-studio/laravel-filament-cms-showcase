<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla tabeli plugin_installations.
 *
 * Przechowuje informacje o zainstalowanych pluginach na stronach.
 * Każda strona może mieć różne pluginy z własną konfiguracją.
 */
return new class extends Migration
{
    /**
     * Uruchomienie migracji.
     */
    public function up(): void
    {
        Schema::create('plugin_installations', function (Blueprint $table) {
            // Klucz główny
            $table->uuid('id')->primary();

            // Multi-tenancy
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Strona na której zainstalowany plugin
            $table->uuid('site_id');
            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            // Identyfikator pluginu
            $table->string('plugin_slug', 50);

            // Wersja pluginu w momencie instalacji
            $table->string('version', 20);

            // Konfiguracja per-site (JSON)
            $table->json('config')->nullable();

            // Status instalacji
            $table->enum('status', ['active', 'disabled', 'pending_upgrade'])
                ->default('active');

            // Kiedy i przez kogo zainstalowano
            $table->timestamp('installed_at');
            $table->uuid('installed_by')->nullable();
            $table->foreign('installed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Timestamps
            $table->timestamps();

            // Unikalność: jeden plugin na stronę
            $table->unique(['site_id', 'plugin_slug']);

            // Indeksy
            $table->index('tenant_id');
            $table->index('plugin_slug');
            $table->index('status');
        });
    }

    /**
     * Cofnięcie migracji.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_installations');
    }
};
