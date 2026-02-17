<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela pivot: użytkownicy ↔ strony z uprawnieniami.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_user', function (Blueprint $table): void {
            $table->id();

            $table->uuid('site_id');
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Rola
            $table->enum('role', ['admin', 'editor', 'viewer'])->default('viewer');

            // Uprawnienia szczegółowe
            $table->boolean('can_publish')->default(false);
            $table->boolean('can_manage_media')->default(true);
            $table->boolean('can_view_analytics')->default(true);

            // Daty
            $table->uuid('invited_by')->nullable();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_access_at')->nullable();

            $table->timestamps();

            // Foreign key
            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            // Unikalność
            $table->unique(['site_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');
    }
};
