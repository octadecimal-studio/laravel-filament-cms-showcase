<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dla tabeli rezerwacji pluginu Reservations.
 *
 * Tabela przechowuje dane z formularzy rezerwacji z frontendu.
 * Prefiks 'plugin_reservations_' dla izolacji danych pluginu.
 */
return new class extends Migration
{
    /**
     * Uruchomienie migracji.
     */
    public function up(): void
    {
        Schema::create('plugin_reservations_reservations', function (Blueprint $table) {
            // Klucz główny
            $table->uuid('id')->primary();

            // Multi-tenancy
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Strona, z której pochodzi rezerwacja
            $table->uuid('site_id');
            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            // Zarezerwowany motocykl (opcjonalny - string z nazwą/ID)
            // Brak FK - dane motocykli są w zewnętrznym systemie (Strapi -> nowy CMS)
            $table->string('motorcycle_id')->nullable();

            // Dane klienta
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);

            // Daty rezerwacji
            $table->date('pickup_date');
            $table->date('return_date');

            // Status rezerwacji
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])
                ->default('pending');

            // Cena (opcjonalna - może być wyliczana)
            $table->decimal('total_price', 10, 2)->nullable();

            // Notatki od klienta
            $table->text('notes')->nullable();

            // RODO compliance
            $table->boolean('rodo_consent')->default(false);
            $table->timestamp('rodo_consent_at')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indeksy
            $table->index(['site_id', 'status'], 'reservations_site_status_idx');
            $table->index(['site_id', 'pickup_date'], 'reservations_site_pickup_idx');
            $table->index('motorcycle_id', 'reservations_moto_idx');
            $table->index('customer_email', 'reservations_email_idx');
        });
    }

    /**
     * Cofnięcie migracji.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_reservations_reservations');
    }
};
