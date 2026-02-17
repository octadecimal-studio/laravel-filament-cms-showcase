<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla Content Blocks - reużywalne bloki treści.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Identyfikacja bloku
            $table->string('name'); // Nazwa bloku
            $table->string('slug')->unique(); // Unikalny identyfikator
            $table->string('category')->nullable(); // Kategoria: hero, features, cta, etc.
            $table->text('description')->nullable();

            // Dane bloku
            $table->json('schema'); // JSON Schema definicja pól
            $table->json('default_data')->nullable(); // Domyślne wartości
            $table->json('config')->nullable(); // Dodatkowa konfiguracja

            // Meta
            $table->string('icon')->nullable(); // Ikona dla UI
            $table->json('preview')->nullable(); // Screenshot lub dane do preview

            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0); // Licznik użyć

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Indeksy
            $table->index('tenant_id');
            $table->index('category');
            $table->index('is_active');
            $table->index(['tenant_id', 'category', 'is_active']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
