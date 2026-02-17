<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabele treściowe MotoRent: brands, categories, motorcycles, gallery, features, process_steps, testimonials.
 * Schema odtworzona z backup_motorent_20260204.sql (produkcja api.example.test).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Brands
        Schema::create('two_wheels_motorcycle_brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100)->unique()->index();
            $table->text('description')->nullable();
            $table->foreignUuid('logo_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Categories
        Schema::create('two_wheels_motorcycle_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100)->unique()->index();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#dc2626');
            $table->boolean('published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Motorcycles (depends on brands, categories)
        Schema::create('two_wheels_motorcycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique()->index();
            $table->foreignUuid('brand_id')->index()->constrained('two_wheels_motorcycle_brands')->restrictOnDelete();
            $table->foreignUuid('category_id')->index()->constrained('two_wheels_motorcycle_categories')->restrictOnDelete();
            $table->foreignUuid('main_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->integer('engine_capacity');
            $table->integer('year');
            $table->decimal('price_per_day', 10, 2);
            $table->decimal('price_per_week', 10, 2);
            $table->decimal('price_per_month', 10, 2);
            $table->decimal('deposit', 10, 2);
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->boolean('available')->default(true)->index();
            $table->boolean('featured')->default(false)->index();
            $table->boolean('published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Motorcycle Gallery (pivot: motorcycles ↔ media)
        Schema::create('two_wheels_motorcycle_gallery', function (Blueprint $table) {
            $table->foreignUuid('motorcycle_id')->constrained('two_wheels_motorcycles')->cascadeOnDelete();
            $table->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();
            $table->integer('order')->default(0)->index();
            $table->timestamps();

            $table->primary(['motorcycle_id', 'media_id']);
        });

        // 5. Features
        Schema::create('two_wheels_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->foreignUuid('icon_id')->nullable()->constrained('media')->nullOnDelete();
            $table->integer('order')->default(0)->index();
            $table->boolean('published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Process Steps
        Schema::create('two_wheels_process_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->integer('step_number');
            $table->string('title');
            $table->text('description');
            $table->string('icon_name', 50);
            $table->boolean('published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'step_number']);
        });

        // 7. Testimonials (depends on motorcycles)
        Schema::create('two_wheels_testimonials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->constrained('tenants')->cascadeOnDelete();
            $table->string('author_name');
            $table->text('content');
            $table->integer('rating');
            $table->foreignUuid('motorcycle_id')->nullable()->constrained('two_wheels_motorcycles')->nullOnDelete();
            $table->integer('order')->default(0)->index();
            $table->boolean('published')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_wheels_testimonials');
        Schema::dropIfExists('two_wheels_process_steps');
        Schema::dropIfExists('two_wheels_features');
        Schema::dropIfExists('two_wheels_motorcycle_gallery');
        Schema::dropIfExists('two_wheels_motorcycles');
        Schema::dropIfExists('two_wheels_motorcycle_categories');
        Schema::dropIfExists('two_wheels_motorcycle_brands');
    }
};
