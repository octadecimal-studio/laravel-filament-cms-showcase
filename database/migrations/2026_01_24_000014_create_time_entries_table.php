<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela wpisów czasu pracy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Kto
            $table->uuid('user_id')->constrained()->onDelete('cascade');

            // Powiązania (opcjonalne)
            $table->uuid('listing_id')->nullable();
            $table->uuid('site_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->uuid('correction_id')->nullable();

            // Opis
            $table->string('description', 500);

            // Czas
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable(); // Obliczone lub ręczne

            // Billing
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_billed')->default(false);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->uuid('invoice_id')->nullable();

            // Kategoria
            $table->enum('category', [
                'sales',        // Sprzedaż (spec templates)
                'development',  // Programowanie
                'design',       // Grafika
                'content',      // Treści
                'support',      // Wsparcie
                'meeting',      // Spotkania
                'revision',     // Poprawki
                'admin',        // Administracja
                'other'
            ])->default('development');

            $table->timestamps();

            // Foreign keys (opcjonalne)
            $table->foreign('listing_id')
                ->references('id')
                ->on('listings')
                ->onDelete('set null');

            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('set null');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            $table->foreign('correction_id')
                ->references('id')
                ->on('corrections')
                ->onDelete('set null');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('set null');

            // Indeksy
            $table->index('user_id');
            $table->index('order_id');
            $table->index('started_at');
            $table->index('is_billable');
            $table->index('is_billed');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
