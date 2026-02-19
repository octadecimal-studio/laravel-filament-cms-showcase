<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New columns on two_wheels_site_settings
        Schema::table('two_wheels_site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('two_wheels_site_settings', 'google_analytics_code')) {
                $table->text('google_analytics_code')->nullable()->after('map_coordinates');
                $table->string('pricing_title')->nullable()->default('Cennik')->after('google_analytics_code');
                $table->text('pricing_subtitle')->nullable()->after('pricing_title');
                $table->string('location_title')->nullable()->default('Lokalizacja')->after('pricing_subtitle');
                $table->text('location_description')->nullable()->after('location_title');
                $table->string('reservation_form_type')->default('internal')->after('location_description');
                $table->string('reservation_form_external_url')->nullable()->after('reservation_form_type');
                $table->string('reservation_notification_email')->nullable()->after('reservation_form_external_url');
            }
        });

        // New table: two_wheels_rental_conditions
        Schema::create('two_wheels_rental_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('title');
            $table->text('description');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'tw_rental_cond_tenant_active_sort_idx');
        });

        // New table: two_wheels_pricing_notes
        Schema::create('two_wheels_pricing_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->text('content');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'tw_pricing_notes_tenant_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_wheels_pricing_notes');
        Schema::dropIfExists('two_wheels_rental_conditions');

        Schema::table('two_wheels_site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_analytics_code',
                'pricing_title',
                'pricing_subtitle',
                'location_title',
                'location_description',
                'reservation_form_type',
                'reservation_form_external_url',
                'reservation_notification_email',
            ]);
        });
    }
};
