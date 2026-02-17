<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // Nazwa pluginu (np. "Filament Shield")
            $table->string('package'); // Package Composer (np. "bezhansalleh/filament-shield")
            $table->string('class_name')->nullable(); // FQCN klasy pluginu (np. "BezhanSalleh\FilamentShield\FilamentShieldPlugin")
            $table->text('description')->nullable();
            $table->string('version')->nullable(); // Wersja z composer.json
            $table->string('author')->nullable();
            $table->string('homepage')->nullable();
            $table->string('repository')->nullable();
            $table->json('config')->nullable(); // Konfiguracja pluginu (opcjonalna)
            $table->boolean('is_installed')->default(false); // Czy jest zainstalowany przez Composer
            $table->boolean('is_enabled')->default(false); // Czy jest włączony w panelu
            $table->boolean('is_official')->default(false); // Czy to oficjalny plugin Filament
            $table->string('category')->nullable(); // Kategoria (security, ui, content, etc.)
            $table->json('tags')->nullable(); // Tagi dla wyszukiwania
            $table->integer('downloads_count')->default(0); // Liczba pobrań (z Packagist)
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique('package');
            $table->index(['is_enabled', 'is_installed']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
