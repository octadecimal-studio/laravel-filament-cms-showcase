<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela poprawek zgłaszanych przez klientów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrections', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Powiązania
            $table->uuid('order_id');
            $table->uuid('site_id');

            // Kto zgłosił
            $table->uuid('reported_by');
            $table->foreign('reported_by')->references('id')->on('users')->onDelete('cascade');

            // Treść
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('page_url', 500)->nullable();  // Gdzie jest problem

            // Status
            $table->enum('status', [
                'reported',       // Zgłoszone
                'accepted',       // Zaakceptowane do realizacji
                'rejected',       // Odrzucone (poza zakresem)
                'in_progress',    // W realizacji
                'done',           // Wykonane
                'verified',       // Zweryfikowane przez klienta
                'deployed'        // Wdrożone na produkcję
            ])->default('reported');

            // Billing
            $table->boolean('is_free')->default(true);    // W ramach darmowego okresu
            $table->decimal('estimated_price', 10, 2)->nullable(); // Jeśli płatna
            $table->text('rejection_reason')->nullable();

            // Przypisanie
            $table->uuid('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();

            // Daty
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('deployed_at')->nullable();

            // Kto zweryfikował
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('cascade');

            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            // Indeksy
            $table->index('order_id');
            $table->index('site_id');
            $table->index('status');
            $table->index('is_free');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrections');
    }
};
