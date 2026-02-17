<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela środowisk stron (staging, production).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_environments', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->uuid('site_id');

            // Typ środowiska
            $table->enum('type', ['staging', 'production']);
            $table->string('url', 500)->nullable();

            // Status deploy
            $table->enum('deploy_status', ['pending', 'deploying', 'deployed', 'failed'])->default('pending');
            $table->timestamp('deployed_at')->nullable();
            $table->text('deploy_logs')->nullable();

            // Konfiguracja
            $table->json('env_variables')->nullable();    // Zmienne środowiskowe (encrypted)

            $table->timestamps();

            // Foreign key
            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            // Unikalność - jedna strona może mieć tylko jedno środowisko każdego typu
            $table->unique(['site_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_environments');
    }
};
