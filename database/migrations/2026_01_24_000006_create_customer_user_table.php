<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela pivot: użytkownicy ↔ klienci z rolami.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_user', function (Blueprint $table): void {
            $table->id();

            $table->uuid('customer_id');
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Rola w kontekście klienta
            $table->enum('role', ['owner', 'admin', 'billing', 'member'])->default('member');

            // Uprawnienia szczegółowe (override roli)
            $table->boolean('can_view_billing')->default(false);
            $table->boolean('can_manage_users')->default(false);

            // Powiadomienia
            $table->boolean('notify_new_invoice')->default(true);
            $table->boolean('notify_site_updates')->default(true);

            // Daty
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();

            // Foreign key
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            // Unikalność
            $table->unique(['customer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user');
    }
};
