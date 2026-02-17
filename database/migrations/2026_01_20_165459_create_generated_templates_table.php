<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla Generated Templates - wygenerowane szablony przez AI.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('generated_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('prompt_template_id')->nullable();

            // Input
            $table->text('prompt'); // Użyty prompt
            $table->string('image_url')->nullable(); // URL obrazka (Vision API)
            $table->string('model')->default('claude-sonnet-4'); // Model AI

            // Status i wynik
            $table->string('status')->default('pending'); // pending, generating, completed, failed
            $table->json('generated_code')->nullable(); // Wygenerowany kod
            $table->json('metadata')->nullable(); // Tokens, cost, time, etc.
            $table->text('error_message')->nullable(); // Komunikat błędu
            $table->decimal('success_score', 3, 2)->nullable(); // Ocena jakości 0.00-1.00

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('prompt_template_id')
                ->references('id')
                ->on('prompt_templates')
                ->onDelete('set null');

            // Indeksy
            $table->index('tenant_id');
            $table->index('status');
            $table->index('model');
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_templates');
    }
};
