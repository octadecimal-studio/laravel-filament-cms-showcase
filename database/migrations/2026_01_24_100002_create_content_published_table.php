<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla ContentPublished - tracking publikacji na środowiskach.
 *
 * Pozwala śledzić która wersja kontentu jest opublikowana
 * na staging vs production dla każdej strony.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('content_published', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Powiązanie z site_content
            $table->uuid('content_id');

            // Środowisko
            $table->enum('environment', ['staging', 'production'])->default('staging');

            // Która wersja jest opublikowana
            $table->uuid('version_id');

            // Kiedy i przez kogo
            $table->timestamp('published_at');
            $table->uuid('published_by')->nullable();

            // Metadata publikacji
            $table->json('publish_notes')->nullable(); // Notatki do publikacji
            $table->boolean('auto_published')->default(false); // Czy auto-publish

            $table->timestamps();

            // Foreign keys
            $table->foreign('content_id')
                ->references('id')
                ->on('site_contents')
                ->onDelete('cascade');

            $table->foreign('version_id')
                ->references('id')
                ->on('content_versions')
                ->onDelete('cascade');

            $table->foreign('published_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Unique constraint: jeden content może mieć tylko jedną publikację per environment
            $table->unique(['content_id', 'environment'], 'content_env_unique');

            // Indeksy
            $table->index('content_id');
            $table->index('environment');
            $table->index('version_id');
            $table->index('published_by');
            $table->index('published_at');
            $table->index(['content_id', 'environment']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_published');
    }
};
