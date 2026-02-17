<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja tworzącatabele dla Content Management System.
 *
 * SiteContent - główna tabela treści (strony, sekcje, komponenty, bloki)
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('project_id')->nullable(); // Opcjonalne przypisanie do projektu

            // Typ treści
            $table->enum('type', ['page', 'section', 'component', 'block'])->default('page');

            // Podstawowe dane
            $table->string('title');
            $table->string('slug')->nullable(); // Dla page
            $table->text('description')->nullable();

            // Treść
            $table->json('data'); // Flexible content - struktura JSON
            $table->json('meta')->nullable(); // SEO meta, OG tags, itp.

            // Status i publikacja
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();

            // Sortowanie i hierarchia
            $table->integer('order')->default(0);
            $table->uuid('parent_id')->nullable(); // Dla hierarchii (np. section w page)

            // Wersjonowanie
            $table->boolean('is_current_version')->default(true);
            $table->integer('version')->default(1);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('parent_id')
                ->references('id')
                ->on('site_contents')
                ->onDelete('cascade');

            // Indeksy
            $table->index('tenant_id');
            $table->index('project_id');
            $table->index('type');
            $table->index('status');
            $table->index('slug');
            $table->index(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'type', 'status']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
