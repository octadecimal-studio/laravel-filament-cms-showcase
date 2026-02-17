<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela zleceń - główny model workflow sprzedaży.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Powiązania
            $table->uuid('customer_id');
            $table->uuid('site_id')->nullable();
            $table->uuid('listing_id')->nullable();
            $table->uuid('spec_template_id')->nullable();
            $table->uuid('parent_order_id')->nullable();  // Dla zleceń rozwojowych

            // Identyfikacja
            $table->string('order_number', 30)->unique(); // ZLC-2026-0001

            // Typ
            $table->enum('type', [
                'new_site',       // Nowa strona
                'development',    // Rozwój istniejącej
                'maintenance',    // Utrzymanie
                'support'         // Wsparcie/poprawki
            ])->default('new_site');

            // Status
            $table->enum('status', [
                'offer_sent',       // Oferta wysłana
                'accepted',         // Klient zaakceptował
                'in_progress',      // W realizacji
                'delivered',        // Dostarczone (czeka na płatność)
                'paid',             // Opłacone
                'completed',        // Zakończone
                'cancelled',        // Anulowane
                'dispute_useme',    // Spór na Useme
                'dispute_resolved', // Spór rozwiązany
                'dispute_lost',     // Spór przegrany
                'transferred'       // Przeniesione do innego wykonawcy
            ])->default('offer_sent');

            // Zakres i opis (zamiast Proposal)
            $table->string('title');
            $table->text('scope')->nullable();            // Tekstowy opis zakresu
            $table->text('requirements')->nullable();     // Wymagania klienta

            // Finanse
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('PLN');

            // Terminy
            $table->integer('estimated_days')->nullable();
            $table->date('deadline_at')->nullable();
            $table->timestamp('free_corrections_until')->nullable(); // Miesiąc na poprawki

            // Linki zewnętrzne
            $table->string('useme_offer_url', 500)->nullable();

            // Daty workflow
            $table->timestamp('offer_sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Problemy
            $table->text('cancellation_reason')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->string('dispute_url', 500)->nullable();
            $table->text('dispute_resolution')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('transferred_to')->nullable();
            $table->text('transferred_reason')->nullable();
            $table->timestamp('transferred_at')->nullable();

            // Notatki
            $table->text('internal_notes')->nullable();

            // Przypisanie
            $table->uuid('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('set null');

            $table->foreign('listing_id')
                ->references('id')
                ->on('listings')
                ->onDelete('set null');

            $table->foreign('spec_template_id')
                ->references('id')
                ->on('spec_templates')
                ->onDelete('set null');

            $table->foreign('parent_order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            // Indeksy
            $table->index('customer_id');
            $table->index('site_id');
            $table->index('status');
            $table->index('type');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
