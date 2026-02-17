<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla Content Templates - szablony stron i sekcji.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('content_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Identyfikacja szablonu
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable(); // page, section, email, etc.
            $table->text('description')->nullable();

            // Struktura szablonu
            $table->json('structure'); // Definicja bloków i layoutu
            $table->json('default_data')->nullable(); // Domyślne dane dla bloków
            $table->json('config')->nullable(); // Konfiguracja (colors, fonts, spacing)

            // Meta
            $table->json('preview')->nullable(); // Screenshot lub preview data
            $table->string('thumbnail_url')->nullable(); // URL do miniaturki
            $table->json('tags')->nullable(); // Tagi dla wyszukiwania

            // Status i popularność
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->integer('usage_count')->default(0);
            $table->decimal('rating', 3, 2)->nullable(); // 0.00-5.00

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
            $table->index('is_premium');
            $table->index(['tenant_id', 'category', 'is_active']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_templates');
    }
};
