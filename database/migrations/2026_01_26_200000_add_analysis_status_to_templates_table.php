<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaj kolumny analysis_status i analysis_progress do templates.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->enum('analysis_status', ['pending', 'analyzing', 'completed', 'failed'])
                ->default('pending')
                ->after('thumbnail_url');
                
            $table->integer('analysis_progress')
                ->default(0)
                ->after('analysis_status')
                ->comment('Progress analizy AI w % (0-100)');
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['analysis_status', 'analysis_progress']);
        });
    }
};
