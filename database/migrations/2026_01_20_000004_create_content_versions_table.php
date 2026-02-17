<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla Content Versions - wersjonowanie treści.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('content_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Powiązanie z wersjonowanym modelem (polymorphic)
            $table->uuid('versionable_id');
            $table->string('versionable_type');

            // Wersja
            $table->integer('version')->default(1);
            $table->boolean('is_current')->default(false);

            // Snapshot danych
            $table->json('data'); // Pełny snapshot modelu
            $table->json('changes')->nullable(); // Diff z poprzednią wersją

            // Metadata
            $table->string('change_summary')->nullable(); // Krótki opis zmian
            $table->uuid('created_by')->nullable(); // User ID
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Timestamps
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indeksy
            $table->index('tenant_id');
            $table->index(['versionable_id', 'versionable_type']);
            $table->index('version');
            $table->index('is_current');
            $table->index('created_by');
            $table->index(['tenant_id', 'versionable_type', 'is_current']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
