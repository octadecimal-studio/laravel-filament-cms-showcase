<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\RevalidationController;
use App\Http\Controllers\Api\TemplateController;
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
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// OpenAPI Specification endpoint
Route::get('/openapi', [OpenApiController::class, 'spec'])->name('api.openapi');

// API v1 endpoints
// Note: tenant is identified by middleware, not route parameter
Route::prefix('v1')->group(function () {
    // Content API (legacy)
    Route::get('/content', [ContentController::class, 'index'])->name('api.v1.content.index');
    
    // Template management
    Route::post('/templates/register', [TemplateController::class, 'register'])->name('api.v1.templates.register');
    Route::get('/templates/{slug}/webhook/test', [TemplateController::class, 'testWebhook'])->name('api.v1.templates.webhook.test');
    
    // Revalidation (for testing)
    Route::post('/templates/{slug}/revalidate', [RevalidationController::class, 'trigger'])->name('api.v1.templates.revalidate');

    // ============================================================
    // NEW API V1 - Site-based content API (2026-01-24)
    // ============================================================

    // System endpoints
    Route::get('/health', [HealthController::class, 'check'])->name('api.v1.health');

    // Webhooks
    Route::post('/webhooks/revalidate', [V1RevalidationController::class, 'handle'])->name('api.v1.webhooks.revalidate');

    // Site-scoped endpoints
    Route::prefix('sites/{slug}')->middleware([IdentifySite::class])->group(function () {
        // Content API
        Route::get('/content', [V1ContentController::class, 'show'])->name('api.v1.sites.content');
        Route::get('/content/{section}', [V1ContentController::class, 'section'])->name('api.v1.sites.content.section');

        // Note: Motorcycles API usunięte - dane będą zarządzane przez zewnętrzny CMS
        // lub nowy system Content (w przyszłości)
    });
});

// ============================================================
// MotoRent Demo API (public, no auth)
// ============================================================
// Dedykowane API dla frontendu example-rental.test
// Tenant: header X-Tenant-ID, query param tenant_id, lub fallback demo-studio.
// Administrator (super_admin) z sesją: bez tenant_id → zwraca dane WSZYSTKICH
// tenantów (kontent klientów). Z tenant_id → dany tenant.

Route::prefix('motorent')->middleware('web')->group(function () {
    // Motocykle
    Route::get('/motorcycles', [TwoWheelsController::class, 'motorcycles'])
        ->name('api.motorent.motorcycles');
    Route::get('/motorcycles/{slug}', [TwoWheelsController::class, 'motorcycle'])
        ->name('api.motorent.motorcycle');

    // Marki
    Route::get('/brands', [TwoWheelsController::class, 'brands'])
        ->name('api.motorent.brands');

    // Kategorie
    Route::get('/categories', [TwoWheelsController::class, 'categories'])
        ->name('api.motorent.categories');

    // Features
    Route::get('/features', [TwoWheelsController::class, 'features'])
        ->name('api.motorent.features');

    // Testimonials
    Route::get('/testimonials', [TwoWheelsController::class, 'testimonials'])
        ->name('api.motorent.testimonials');

    // Process Steps
    Route::get('/process-steps', [TwoWheelsController::class, 'processSteps'])
        ->name('api.motorent.process-steps');

    // Site Setting
    Route::get('/site-setting', [TwoWheelsController::class, 'siteSetting'])
        ->name('api.motorent.site-setting');

    // Gallery (title, subtitle, images z CMS – format pod Next.js Gallery.tsx)
    Route::get('/gallery', [TwoWheelsController::class, 'gallery'])
        ->name('api.motorent.gallery');
});

// ============================================================
// Menu API (public, dla frontendu)
// ============================================================
// Endpoint do pobierania menu dla danej lokacji

Route::prefix('menus')->group(function () {
    Route::get('/location/{location}', [MenuController::class, 'getByLocation'])
        ->name('api.menus.location');
});

// ============================================================
// Reservations Plugin API (public, for example-rental.test form)
// ============================================================
// Endpoint do tworzenia rezerwacji z formularza na stronie
// Site jest identyfikowana przez slug w URL

Route::prefix('v1/sites/{siteSlug}/plugins/reservations')->group(function () {
    // Tworzenie rezerwacji (POST)
    Route::post('/', [ReservationController::class, 'store'])
        ->name('api.v1.plugins.reservations.store');

    // Szczegóły rezerwacji (GET)
    Route::get('/{reservation}', [ReservationController::class, 'show'])
        ->name('api.v1.plugins.reservations.show');

    // Lista rezerwacji (GET) - opcjonalnie dla testów
    Route::get('/', [ReservationController::class, 'index'])
        ->name('api.v1.plugins.reservations.index');
});
