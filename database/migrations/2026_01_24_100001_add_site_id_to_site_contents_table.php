<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migracja dodająca powiązanie site_contents z sites.
 *
 * Umożliwia przypisanie kontentu do konkretnej strony klienta,
 * co jest kluczowe dla API frontendowego.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::table('site_contents', function (Blueprint $table) {
            // Dodaj site_id po tenant_id
            $table->uuid('site_id')->nullable()->after('tenant_id');

            // Foreign key do sites
            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            // Indeksy
            $table->index('site_id');
            $table->index(['site_id', 'type', 'status']);
            $table->index(['site_id', 'slug']);
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::table('site_contents', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropIndex(['site_id']);
            $table->dropIndex(['site_id', 'type', 'status']);
            $table->dropIndex(['site_id', 'slug']);
            $table->dropColumn('site_id');
        });
    }
};
