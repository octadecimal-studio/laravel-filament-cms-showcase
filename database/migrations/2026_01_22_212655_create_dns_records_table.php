<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja tworząca tabelę rekordów DNS dla Deployment Pipeline.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('domain_id'); // Relacja do domains

            // Typ i dane rekordu DNS
            $table->enum('type', ['A', 'AAAA', 'MX', 'TXT', 'CNAME', 'NS'])->default('A');
            $table->string('subdomain')->nullable(); // Subdomena (puste dla root domain)
            $table->string('target'); // Wartość rekordu (IP, hostname, tekst)
            $table->integer('priority')->nullable(); // Dla rekordów MX
            $table->integer('ttl')->default(3600); // Time to live w sekundach

            // Status i synchronizacja z OVH
            $table->string('ovh_record_id')->nullable(); // ID rekordu w OVH API
            $table->enum('status', ['pending', 'synced', 'failed'])->default('pending');
            $table->timestamp('synced_at')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('domain_id')
                ->references('id')
                ->on('domains')
                ->onDelete('cascade');

            // Indeksy
            $table->index('domain_id');
            $table->index('type');
            $table->index('status');
            $table->index(['domain_id', 'type', 'subdomain']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
