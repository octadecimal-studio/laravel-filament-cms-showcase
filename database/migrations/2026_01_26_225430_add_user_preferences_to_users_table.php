<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('email');
            $table->json('theme_colors')->nullable()->after('avatar_url'); // primary, secondary, accent, etc.
            $table->string('wallpaper_url')->nullable()->after('theme_colors');
            $table->json('panel_preferences')->nullable()->after('wallpaper_url'); // sidebar_width, etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_url', 'theme_colors', 'wallpaper_url', 'panel_preferences']);
        });
    }
};
