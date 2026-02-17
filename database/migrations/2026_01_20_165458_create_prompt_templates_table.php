<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla Prompt Templates - biblioteka promptów AI.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Identyfikacja
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category'); // hero, features, gallery, contact, full-page
            $table->text('prompt_text'); // Tekst promptu z zmiennymi {{variable}}
            $table->json('variables')->nullable(); // Lista zmiennych w prompcie
            $table->text('description')->nullable();

            // Statystyki
            $table->integer('usage_count')->default(0);
            $table->decimal('success_rate', 3, 2)->nullable(); // 0.00-1.00
            $table->decimal('avg_score', 3, 2)->nullable(); // 0.00-1.00
            $table->json('examples')->nullable(); // Przykłady użycia

            // Status
            $table->boolean('is_active')->default(true);

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
        Schema::dropIfExists('prompt_templates');
    }
};
