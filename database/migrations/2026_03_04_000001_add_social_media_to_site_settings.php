<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_wheels_site_settings', function (Blueprint $table) {
            $table->json('social_media')->nullable()->after('google_analytics_code');
        });
    }

    public function down(): void
    {
        Schema::table('two_wheels_site_settings', function (Blueprint $table) {
            $table->dropColumn('social_media');
        });
    }
};
