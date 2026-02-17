<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja tworząca tabelę dla Media Manager.
 *
 * Przechowuje metadane plików (obrazy, dokumenty, video).
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('project_id')->nullable(); // Opcjonalne przypisanie do projektu

            // Podstawowe informacje o pliku
            $table->string('file_name'); // Oryginalna nazwa pliku
            $table->string('file_path'); // Ścieżka w storage (relative)
            $table->string('mime_type'); // image/jpeg, application/pdf, etc.
            $table->unsignedBigInteger('size'); // Rozmiar w bajtach
            $table->string('disk')->default('local'); // Storage disk (local, s3, etc.)

            // Dla obrazów
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Opis i metadane
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->json('metadata')->nullable(); // EXIF, IPTC, etc.
            $table->json('variants')->nullable(); // Różne rozmiary (thumbnail, medium, large)
            $table->json('dominant_colors')->nullable(); // Dominujące kolory (RGB)

            // Organizacja
            $table->string('collection')->nullable(); // Kategoria/collection (gallery, documents, etc.)
            $table->json('tags')->nullable(); // Tagi dla wyszukiwania

            // Status
            $table->boolean('is_public')->default(false); // Czy publiczny (dostępny bez auth)
            $table->boolean('is_active')->default(true);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Indexes
            $table->index('tenant_id');
            $table->index('project_id');
            $table->index('collection');
            $table->index('mime_type');
            $table->index('is_public');
            $table->index('is_active');
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
