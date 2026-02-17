<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja tworząca tabelę domen dla Deployment Pipeline.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('project_id')->nullable(); // Opcjonalne przypisanie do projektu

            // Podstawowe dane domeny
            $table->string('domain')->unique(); // Pełna nazwa domeny (np. example.com)
            $table->string('subdomain')->nullable(); // Subdomena (np. dev, www)

            // Status DNS i SSL
            $table->enum('dns_status', ['pending', 'propagating', 'active', 'failed'])->default('pending');
            $table->enum('ssl_status', ['pending', 'requested', 'active', 'expired', 'failed'])->default('pending');
            $table->timestamp('dns_checked_at')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();

            // Konfiguracja
            $table->string('vps_ip')->nullable(); // IP serwera VPS
            $table->string('mail_hostname')->nullable(); // Hostname dla email (np. mail.example.com)

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
            $table->index('project_id');
            $table->index('domain');
            $table->index('dns_status');
            $table->index('ssl_status');
            $table->index(['tenant_id', 'domain']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
