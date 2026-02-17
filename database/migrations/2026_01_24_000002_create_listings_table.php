<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela ogłoszeń z portali (useme, oferteo, itp.) - punkt startowy dla spec templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Źródło
            $table->string('platform', 50);               // useme, oferteo, direct
            $table->string('external_id', 100)->nullable(); // ID z portalu
            $table->string('url', 500);                   // Link do ogłoszenia

            // Treść ogłoszenia
            $table->string('title');
            $table->text('description')->nullable();

            // Budżet klienta
            $table->decimal('budget_min', 10, 2)->nullable();
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->string('currency', 3)->default('PLN');

            // Termin
            $table->date('deadline')->nullable();

            // Dane klienta z ogłoszenia
            $table->string('client_name')->nullable();    // Nick/nazwa z portalu
            $table->string('client_location')->nullable();

            // Status
            $table->enum('status', [
                'new',              // Nowe ogłoszenie
                'spec_in_progress', // Przygotowuję spec template
                'spec_ready',       // Spec gotowy
                'offer_sent',       // Oferta wysłana
                'won',              // Wygrałem
                'lost',             // Przegrałem
                'expired',          // Ogłoszenie wygasło
                'skipped'           // Pominąłem
            ])->default('new');

            // Ocena
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->text('notes')->nullable();

            // Daty
            $table->timestamp('found_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indeksy
            $table->index('platform');
            $table->index('status');
            $table->index('priority');
            $table->unique(['platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
