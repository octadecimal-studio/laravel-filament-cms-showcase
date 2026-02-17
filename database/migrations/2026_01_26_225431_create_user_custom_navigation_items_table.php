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
        Schema::create('user_custom_navigation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('label');
            $table->string('icon')->nullable();
            $table->string('url');
            $table->string('group')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_pinned_to_topbar')->default(false); // Czy w górnym menu (zakładki)
            $table->boolean('is_active')->default(true);
            $table->boolean('open_in_new_tab')->default(false);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['user_id', 'sort_order']);
            $table->index(['user_id', 'is_pinned_to_topbar']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_custom_navigation_items');
    }
};
