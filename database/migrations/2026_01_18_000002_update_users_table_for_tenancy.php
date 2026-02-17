<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja aktualizująca tabelę users dla systemu multi-tenancy.
 */
return new class extends Migration
{
    /**
     * Uruchom migrację.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Zmień id na UUID (musi być przed dodaniem foreign key)
            // Uwaga: wymaga pustej tabeli lub migracji danych
        });

        // Usuń starą tabelę i utwórz nową z UUID
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Dane użytkownika
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Multi-tenancy
            $table->uuid('tenant_id')->nullable();
            $table->boolean('is_super_admin')->default(false);

            // Timestampy
            $table->timestamps();

            // Foreign key do tenantów
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Indeksy
            $table->index('tenant_id');
            $table->index('is_super_admin');
            $table->index(['tenant_id', 'email']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
