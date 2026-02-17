<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela domen przypisanych do stron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->uuid('site_id');

            // Domena
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);

            // Status DNS i SSL
            $table->enum('dns_status', ['pending', 'propagating', 'active', 'failed'])->default('pending');
            $table->enum('ssl_status', ['pending', 'provisioning', 'active', 'expired', 'failed'])->default('pending');

            // Konfiguracja DNS
            $table->json('dns_records')->nullable();      // Wymagane rekordy DNS

            // SSL
            $table->timestamp('ssl_expires_at')->nullable();

            // Weryfikacja
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            // Foreign key
            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            // Indeksy
            $table->index('site_id');
            $table->index('dns_status');
            $table->index('ssl_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_domains');
    }
};
