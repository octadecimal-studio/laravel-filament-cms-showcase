<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela płatności.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Powiązania
            $table->uuid('customer_id');
            $table->uuid('invoice_id')->nullable();
            $table->uuid('order_id')->nullable();

            // Kwota
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PLN');

            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded'])->default('pending');

            // Metoda
            $table->enum('payment_method', ['useme', 'transfer', 'card', 'blik', 'cash', 'other'])->nullable();

            // Stripe
            $table->string('stripe_payment_intent_id', 100)->nullable();
            $table->string('stripe_charge_id', 100)->nullable();

            // Dane transakcji
            $table->string('transaction_id', 100)->nullable();

            // Daty
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('set null');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            // Indeksy
            $table->index('customer_id');
            $table->index('invoice_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
