<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela pozycji na fakturach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->uuid('invoice_id');

            // Opis
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('szt');   // szt, h, msc
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(23); // VAT %
            $table->decimal('total', 12, 2);

            // Powiązanie z zamówieniem (opcjonalne)
            $table->uuid('order_id')->nullable();

            // Kolejność
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Foreign keys
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            // Indeks
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
