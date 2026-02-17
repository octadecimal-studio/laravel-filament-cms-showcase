<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela spekulatywnych szablonów - przygotowane przed kontaktem z klientem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spec_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Powiązania
            $table->uuid('listing_id');
            $table->uuid('template_id')->nullable();      // Użyty szablon bazowy

            // Propozycja cenowa
            $table->decimal('proposed_price', 10, 2);
            $table->integer('proposed_days');

            // Preview
            $table->string('preview_url', 500)->nullable();
            $table->string('screenshot_url', 500)->nullable();

            // Customizacja
            $table->json('customizations')->nullable();   // Kolory, teksty, itp.

            // Status
            $table->enum('status', ['draft', 'ready', 'sent', 'won', 'lost'])->default('draft');

            // Notatki
            $table->text('notes')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('listing_id')
                ->references('id')
                ->on('listings')
                ->onDelete('cascade');

            $table->foreign('template_id')
                ->references('id')
                ->on('templates')
                ->onDelete('set null');

            // Indeksy
            $table->index('listing_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spec_templates');
    }
};
