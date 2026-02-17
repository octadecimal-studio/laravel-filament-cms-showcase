<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\TwoWheelsController;
use App\Http\Controllers\Api\V1\Public\ContentController as V1ContentController;
use App\Http\Controllers\Api\V1\System\HealthController;
use App\Http\Controllers\Api\V1\Webhook\RevalidationController as V1RevalidationController;
use App\Http\Middleware\IdentifySite;
use App\Plugins\Reservations\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// OpenAPI Specification endpoint
Route::get('/openapi', [OpenApiController::class, 'spec'])->name('api.openapi');

// API v1 endpoints
Route::prefix('v1')->group(function () {
    // System endpoints
    Route::get('/health', [HealthController::class, 'check'])->name('api.v1.health');

    // Webhooks
    Route::post('/webhooks/revalidate', [V1RevalidationController::class, 'handle'])->name('api.v1.webhooks.revalidate');

    // Site-scoped endpoints
    Route::prefix('sites/{slug}')->middleware([IdentifySite::class])->group(function () {
        // Content API
        Route::get('/content', [V1ContentController::class, 'show'])->name('api.v1.sites.content');
        Route::get('/content/{section}', [V1ContentController::class, 'section'])->name('api.v1.sites.content.section');
    });
});

// ============================================================
// MotoRent Demo API (public, no auth)
// ============================================================
Route::prefix('motorent')->middleware('web')->group(function () {
    Route::get('/motorcycles', [TwoWheelsController::class, 'motorcycles'])->name('api.motorent.motorcycles');
    Route::get('/motorcycles/{slug}', [TwoWheelsController::class, 'motorcycle'])->name('api.motorent.motorcycle');
    Route::get('/brands', [TwoWheelsController::class, 'brands'])->name('api.motorent.brands');
    Route::get('/categories', [TwoWheelsController::class, 'categories'])->name('api.motorent.categories');
    Route::get('/features', [TwoWheelsController::class, 'features'])->name('api.motorent.features');
    Route::get('/testimonials', [TwoWheelsController::class, 'testimonials'])->name('api.motorent.testimonials');
    Route::get('/process-steps', [TwoWheelsController::class, 'processSteps'])->name('api.motorent.process-steps');
    Route::get('/site-setting', [TwoWheelsController::class, 'siteSetting'])->name('api.motorent.site-setting');
    Route::get('/gallery', [TwoWheelsController::class, 'gallery'])->name('api.motorent.gallery');
});

// ============================================================
// Menu API (public)
// ============================================================
Route::prefix('menus')->group(function () {
    Route::get('/location/{location}', [MenuController::class, 'getByLocation'])->name('api.menus.location');
});

// ============================================================
// Reservations Plugin API
// ============================================================
Route::prefix('v1/sites/{siteSlug}/plugins/reservations')->group(function () {
    Route::post('/', [ReservationController::class, 'store'])->name('api.v1.plugins.reservations.store');
    Route::get('/{reservation}', [ReservationController::class, 'show'])->name('api.v1.plugins.reservations.show');
    Route::get('/', [ReservationController::class, 'index'])->name('api.v1.plugins.reservations.index');
});
