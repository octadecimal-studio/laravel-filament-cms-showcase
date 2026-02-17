<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaj kolumny webhook_url i deployment_env do tabeli templates.
 */
return new class extends Migration
{
    /**
     * Wykonaj migrację.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('webhook_url', 500)->nullable()->after('preview_url');
            $table->string('deployment_env', 10)->nullable()->default('prd')->after('webhook_url');
        });
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'deployment_env']);
        });
    }
};
