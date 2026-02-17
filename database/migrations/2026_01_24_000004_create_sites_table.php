<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela stron internetowych klientów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Powiązania
            $table->uuid('customer_id');
            $table->uuid('template_id')->nullable();

            // Identyfikacja
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 20)->unique()->nullable(); // SITE-0001

            // Backup szablonu
            $table->string('template_slug', 100)->nullable();

            // Status
            $table->enum('status', [
                'development',  // W realizacji
                'live',         // Opublikowana
                'suspended',    // Zawieszona (np. brak płatności)
                'archived'      // Zarchiwizowana
            ])->default('development');

            // URL-e środowisk
            $table->string('staging_url', 500)->nullable();
            $table->string('production_url', 500)->nullable();

            // Ustawienia
            $table->json('settings')->nullable();         // Kolory, fonty, itp.
            $table->json('seo_settings')->nullable();     // SEO defaults

            // Daty lifecycle
            $table->timestamp('published_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            // Cache statystyk
            $table->integer('pages_count')->default(0);
            $table->integer('media_count')->default(0);
            $table->timestamp('last_content_update_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            $table->foreign('template_id')
                ->references('id')
                ->on('templates')
                ->onDelete('set null');

            // Indeksy
            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
