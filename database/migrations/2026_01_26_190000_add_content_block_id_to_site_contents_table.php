<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaj content_block_id do site_contents dla powiązania z ContentBlock.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::table('site_contents', function (Blueprint $table) {
            $table->uuid('content_block_id')->nullable()->after('type');
            
            $table->foreign('content_block_id')
                ->references('id')
                ->on('content_blocks')
                ->onDelete('set null');
                
            $table->index('content_block_id');
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::table('site_contents', function (Blueprint $table) {
            $table->dropForeign(['content_block_id']);
            $table->dropIndex(['content_block_id']);
            $table->dropColumn('content_block_id');
        });
    }
};
