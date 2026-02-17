<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela klientów (zleceniodawców) - zastępuje koncepcyjnie tenants dla workflow sprzedaży stron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Identyfikacja
            $table->string('name');                          // Nazwa wyświetlana
            $table->string('slug')->unique();                // URL-friendly
            $table->string('code', 20)->unique()->nullable(); // Kod klienta (CLI-001)

            // Dane firmy
            $table->string('company_name')->nullable();      // Pełna nazwa firmy
            $table->string('nip', 20)->nullable();           // NIP
            $table->string('regon', 20)->nullable();         // REGON

            // Kontakt
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website', 500)->nullable();

            // Adres
            $table->string('address_street')->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_postal', 20)->nullable();
            $table->string('address_country', 2)->default('PL');

            // Adres do faktur (jeśli inny)
            $table->string('billing_address_street')->nullable();
            $table->string('billing_address_city', 100)->nullable();
            $table->string('billing_address_postal', 20)->nullable();
            $table->string('billing_address_country', 2)->nullable();

            // Źródło pozyskania
            $table->string('source', 50)->nullable();        // useme, referral, organic, ads
            $table->string('source_url', 500)->nullable();   // Link do ogłoszenia
            $table->string('referral_code', 50)->nullable();

            // Notatki
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();      // Tylko dla admina

            // Status
            $table->enum('status', ['prospect', 'active', 'inactive', 'churned'])->default('prospect');
            $table->boolean('is_vip')->default(false);

            // Limity i ustawienia
            $table->integer('max_sites')->default(10);
            $table->json('settings')->nullable();

            // Daty
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('churned_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indeksy
            $table->index('status');
            $table->index('source');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
