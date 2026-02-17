<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja tworząca tabelę deploymentów dla Deployment Pipeline.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('project_id')->nullable(); // Opcjonalne przypisanie do projektu
            $table->uuid('domain_id')->nullable(); // Opcjonalne przypisanie do domeny

            // Status deploymentu
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'rolled_back'])->default('pending');
            $table->string('version')->nullable(); // Wersja deploymentu (np. 20260122-212654)

            // Logi i informacje
            $table->longText('logs')->nullable(); // Logi deploymentu (JSON array)
            $table->json('metadata')->nullable(); // Dodatkowe metadane (config, environment, etc.)

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('domain_id')
                ->references('id')
                ->on('domains')
                ->onDelete('set null');

            // Indeksy
            $table->index('tenant_id');
            $table->index('project_id');
            $table->index('domain_id');
            $table->index('status');
            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
