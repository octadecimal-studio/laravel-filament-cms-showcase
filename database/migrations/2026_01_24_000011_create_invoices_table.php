<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela faktur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Powiązania
            $table->uuid('customer_id');
            $table->uuid('order_id')->nullable();

            // Identyfikacja
            $table->string('invoice_number', 30)->unique(); // FV/2026/01/0001

            // Status i typ
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled', 'refunded'])->default('draft');
            $table->enum('type', ['invoice', 'proforma', 'correction'])->default('invoice');

            // Kwoty
            $table->decimal('subtotal', 12, 2);           // Netto
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0); // VAT
            $table->decimal('total', 12, 2);              // Brutto
            $table->string('currency', 3)->default('PLN');

            // Daty
            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();

            // Dane nabywcy (snapshot)
            $table->string('buyer_name')->nullable();
            $table->string('buyer_nip', 20)->nullable();
            $table->text('buyer_address')->nullable();

            // PDF
            $table->string('pdf_url', 500)->nullable();

            // Stripe
            $table->string('stripe_invoice_id', 100)->nullable();

            // Notatki
            $table->text('notes')->nullable();

            // Kto wystawił
            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            // Indeksy
            $table->index('customer_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
