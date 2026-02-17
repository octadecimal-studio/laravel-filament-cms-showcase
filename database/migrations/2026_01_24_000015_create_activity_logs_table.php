<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela logów aktywności (audit trail).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Kto
            $table->uuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();      // Backup jeśli user usunięty

            // Na czym (polimorficzne)
            $table->string('subject_type', 100);          // App\Models\Site, App\Models\Order, etc.
            $table->uuid('subject_id');

            // Co
            $table->string('action', 50);                 // created, updated, deleted, published, etc.

            // Szczegóły
            $table->json('properties')->nullable();       // Stare/nowe wartości

            // Kontekst
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at');

            // Indeksy
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
