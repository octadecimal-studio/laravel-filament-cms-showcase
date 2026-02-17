<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla Templates - gotowe szablony Next.js.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Identyfikacja szablonu
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('directory_path'); // Ścieżka względem templates/
            $table->string('category')->nullable(); // portfolio, landing, corporate, blog
            $table->json('tech_stack')->nullable(); // ['Next.js', 'TypeScript', 'Tailwind']
            $table->text('description')->nullable();

            // Metadane
            $table->json('metadata')->nullable(); // Komponenty, style, zależności
            $table->string('thumbnail_url')->nullable(); // URL do miniaturki
            $table->string('preview_url')->nullable(); // URL preview (iframe)
            $table->json('tags')->nullable(); // Tagi dla wyszukiwania

            // Status i popularność
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->integer('usage_count')->default(0);

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
        Schema::dropIfExists('templates');
    }
};
