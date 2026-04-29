<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Site;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Modules\Content\Models\Media;
use App\Mail\ReservationNotification;
use App\Modules\Content\Models\TwoWheels\Feature;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use App\Modules\Content\Models\TwoWheels\MotorcycleBrand;
use App\Modules\Content\Models\TwoWheels\MotorcycleCategory;
use App\Modules\Content\Models\TwoWheels\PricingNote;
use App\Modules\Content\Models\TwoWheels\ProcessStep;
use App\Modules\Content\Models\TwoWheels\RentalCondition;
use App\Modules\Content\Models\TwoWheels\SiteSetting;
use App\Modules\Content\Models\TwoWheels\Testimonial;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Scopes\TenantScope;
use App\Plugins\Reservations\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * API Controller dla MotoRent Demo.
 *
 * Udostępnia dane przez REST API dla strony Next.js.
 * Administrator (super_admin) z sesją: brak tenant_id → zwraca dane wszystkich
 * tenantów. Z tenant_id → dany tenant.
 */
class TwoWheelsController extends Controller
{
    /**
     * Czy request pochodzi od zalogowanego super_admina (sesja web).
     */
    private function isAdminRequest(Request $request): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isSuperAdmin();
    }
    /**
     * Pobiera ustawienia strony (single type).
     */
    public function siteSetting(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $setting = SiteSetting::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->with('logo')
            ->first();

        if (! $setting) {
            return response()->json(['error' => 'Site setting not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $setting->id,
                'site_title' => $setting->site_title,
                'site_description' => $setting->site_description,
                'about_us_content' => $setting->about_us_content,
                'regulamin_content' => $setting->regulamin_content,
                'polityka_prywatnosci_content' => $setting->polityka_prywatnosci_content,
                'logo' => $setting->logo ? [
                    'id' => $setting->logo->id,
                    'url' => $setting->logo->getUrl(),
                ] : null,
                'contact_phone' => $setting->contact_phone,
                'contact_email' => $setting->contact_email,
                'address' => $setting->address,
                'opening_hours' => $setting->opening_hours,
                'map_coordinates' => $setting->map_coordinates,
                'google_analytics_code' => $setting->google_analytics_code,
                'location_title' => $setting->location_title,
                'location_description' => $setting->location_description,
                'pricing_title' => $setting->pricing_title,
                'pricing_subtitle' => $setting->pricing_subtitle,
                'company_data' => $setting->company_data,
                'social_media' => $setting->social_media,
                'pickup_hours' => $setting->pickup_hours ?? ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'],
            ],
        ]);
    }

    /**
     * Pobiera kategorie motocykli.
     */
    public function categories(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = MotorcycleCategory::withoutGlobalScope(TenantScope::class)
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('name');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $categories = $query->get();

        return response()->json([
            'data' => $categories->map(fn ($cat) => [
                'id' => $cat->id,
                'tenant_id' => $cat->tenant_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'description' => $cat->description,
                'color' => $cat->color,
            ]),
        ]);
    }

    /**
     * Pobiera marki motocykli.
     */
    public function brands(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = MotorcycleBrand::withoutGlobalScope(TenantScope::class)
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with('logo')
            ->orderBy('name');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $brands = $query->get();

        return response()->json([
            'data' => $brands->map(fn ($brand) => [
                'id' => $brand->id,
                'tenant_id' => $brand->tenant_id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'logo' => $brand->logo ? [
                    'id' => $brand->logo->id,
                    'url' => $brand->logo->getUrl(),
                ] : null,
            ]),
        ]);
    }

    /**
     * Pobiera motocykle.
     */
    public function motorcycles(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = Motorcycle::withoutGlobalScope(TenantScope::class)
            ->published()
            ->available()
            ->with(['brand', 'category', 'mainImage']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($request->has('featured')) {
            $query->where('featured', $request->boolean('featured'));
        }

        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->input('category'));
            });
        }

        if ($request->has('brand')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('slug', $request->input('brand'));
            });
        }

        $motorcycles = $query->orderByDesc('price_per_day')->get();

        return response()->json([
            'data' => $motorcycles->map(fn ($moto) => [
                'id' => $moto->id,
                'tenant_id' => $moto->tenant_id,
                'name' => $moto->name,
                'slug' => $moto->slug,
                'brand' => $moto->brand ? [
                    'id' => $moto->brand->id,
                    'name' => $moto->brand->name,
                    'slug' => $moto->brand->slug,
                ] : null,
                'category' => $moto->category ? [
                    'id' => $moto->category->id,
                    'name' => $moto->category->name,
                    'slug' => $moto->category->slug,
                    'color' => $moto->category->color,
                ] : null,
                'main_image' => $moto->mainImage ? [
                    'id' => $moto->mainImage->id,
                    'url' => $moto->mainImage->getUrl(),
                    'alt_text' => $moto->mainImage->alt_text,
                ] : null,
                'engine_capacity' => $moto->engine_capacity,
                'year' => $moto->year,
                'price_per_day' => (float) $moto->price_per_day,
                'price_per_week' => (float) $moto->price_per_week,
                'price_per_month' => (float) $moto->price_per_month,
                'deposit' => (float) $moto->deposit,
                'description' => $moto->description,
                'specifications' => $moto->specifications,
                'featured' => $moto->featured,
                'booking_mode' => $moto->booking_mode ?? 'online',
            ]),
        ]);
    }

    /**
     * Pobiera pojedynczy motocykl.
     */
    public function motorcycle(Request $request, string $slug): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $motorcycle = Motorcycle::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->published()
            ->with(['brand', 'category', 'mainImage', 'gallery'])
            ->first();

        if (! $motorcycle) {
            return response()->json(['error' => 'Motorcycle not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $motorcycle->id,
                'name' => $motorcycle->name,
                'slug' => $motorcycle->slug,
                'brand' => $motorcycle->brand ? [
                    'id' => $motorcycle->brand->id,
                    'name' => $motorcycle->brand->name,
                    'slug' => $motorcycle->brand->slug,
                ] : null,
                'category' => $motorcycle->category ? [
                    'id' => $motorcycle->category->id,
                    'name' => $motorcycle->category->name,
                    'slug' => $motorcycle->category->slug,
                    'color' => $motorcycle->category->color,
                ] : null,
                'main_image' => $motorcycle->mainImage ? [
                    'id' => $motorcycle->mainImage->id,
                    'url' => $motorcycle->mainImage->getUrl(),
                    'alt_text' => $motorcycle->mainImage->alt_text,
                ] : null,
                'gallery' => $motorcycle->gallery->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->getUrl(),
                ]),
                'engine_capacity' => $motorcycle->engine_capacity,
                'year' => $motorcycle->year,
                'price_per_day' => (float) $motorcycle->price_per_day,
                'price_per_week' => (float) $motorcycle->price_per_week,
                'price_per_month' => (float) $motorcycle->price_per_month,
                'deposit' => (float) $motorcycle->deposit,
                'description' => $motorcycle->description,
                'specifications' => $motorcycle->specifications,
                'available' => $motorcycle->available,
                'booking_mode' => $motorcycle->booking_mode ?? 'online',
            ],
        ]);
    }

    /**
     * Pobiera features.
     */
    public function features(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = Feature::withoutGlobalScope(TenantScope::class)
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with('icon')
            ->orderBy('order');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $features = $query->get();

        return response()->json([
            'data' => $features->map(fn ($feature) => [
                'id' => $feature->id,
                'tenant_id' => $feature->tenant_id,
                'title' => $feature->title,
                'description' => $feature->description,
                'icon' => $feature->icon ? [
                    'id' => $feature->icon->id,
                    'url' => $feature->icon->getUrl(),
                ] : null,
                'order' => $feature->order,
            ]),
        ]);
    }

    /**
     * Pobiera kroki procesu.
     */
    public function processSteps(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = ProcessStep::withoutGlobalScope(TenantScope::class)
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('step_number');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $steps = $query->get();

        return response()->json([
            'data' => $steps->map(fn ($step) => [
                'id' => $step->id,
                'tenant_id' => $step->tenant_id,
                'step_number' => $step->step_number,
                'title' => $step->title,
                'description' => $step->description,
                'icon_name' => $step->icon_name,
            ]),
        ]);
    }

    /**
     * Pobiera testimonials.
     */
    public function testimonials(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = Testimonial::withoutGlobalScope(TenantScope::class)
            ->where('published', true)
            ->orderBy('order');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $testimonials = $query->get();

        return response()->json([
            'data' => $testimonials->map(fn ($testimonial) => [
                'id' => $testimonial->id,
                'tenant_id' => $testimonial->tenant_id,
                'author_name' => $testimonial->author_name,
                'content' => $testimonial->content,
                'rating' => $testimonial->rating,
                'motorcycle' => $testimonial->motorcycle ? [
                    'id' => $testimonial->motorcycle->id,
                    'name' => $testimonial->motorcycle->name,
                    'slug' => $testimonial->motorcycle->slug,
                ] : null,
            ]),
        ]);
    }

    /**
     * Pobiera dane galerii (title, subtitle, images z CMS).
     * Zgodne z formatem oczekiwanym przez Next.js (Gallery.tsx / getGalleryData).
     */
    public function gallery(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = Media::withoutGlobalScope(TenantScope::class)
            ->where('collection', 'gallery')
            ->where('mime_type', 'like', 'image/%')
            ->orderByDesc('created_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $items = $query->get();

        $images = $items->map(fn ($m) => [
            'id' => $m->id,
            'url' => $m->getUrl(),
            'alt' => $m->alt_text ?? $m->file_name,
        ])->values()->all();

        return response()->json([
            'data' => [
                'title' => 'Galeria',
                'subtitle' => 'Zobacz nasze motocykle w akcji',
                'images' => $images,
            ],
        ]);
    }

    /**
     * Pobiera warunki wypożyczenia.
     */
    public function rentalConditions(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = RentalCondition::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $conditions = $query->get();

        return response()->json([
            'data' => $conditions->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'description' => $c->description,
                'icon' => $c->icon,
                'sort_order' => $c->sort_order,
            ]),
        ]);
    }

    /**
     * Pobiera ustawienia cennika z cenami motocykli i uwagami.
     */
    public function pricingSettings(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $setting = SiteSetting::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->first();

        $motorcycles = Motorcycle::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('published', true)
            ->orderByDesc('price_per_day')
            ->get(['id', 'name', 'slug', 'price_per_day', 'price_per_week', 'price_per_month', 'deposit']);

        $notes = PricingNote::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => [
                'title' => $setting?->pricing_title ?? 'Cennik',
                'subtitle' => $setting?->pricing_subtitle,
                'motorcycles' => $motorcycles->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'slug' => $m->slug,
                    'price_per_day' => (float) $m->price_per_day,
                    'price_per_week' => (float) $m->price_per_week,
                    'price_per_month' => (float) $m->price_per_month,
                    'deposit' => (float) $m->deposit,
                ]),
                'notes' => $notes->map(fn ($n) => [
                    'id' => $n->id,
                    'content' => $n->content,
                ]),
            ],
        ]);
    }

    /**
     * Pobiera uwagi cennika.
     */
    public function pricingNotes(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $this->isAdminRequest($request) && ! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $query = PricingNote::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $notes = $query->get();

        return response()->json([
            'data' => $notes->map(fn ($n) => [
                'id' => $n->id,
                'content' => $n->content,
                'sort_order' => $n->sort_order,
            ]),
        ]);
    }

    /**
     * Pobiera ustawienia formularza rezerwacji.
     */
    public function reservationSettings(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $setting = SiteSetting::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->first();

        return response()->json([
            'data' => [
                'form_type' => $setting?->reservation_form_type ?? 'internal',
                'external_url' => $setting?->reservation_form_external_url,
            ],
        ]);
    }

    /**
     * Przyjmuje rezerwację z formularza i wysyła powiadomienie email.
     */
    public function submitReservation(Request $request): JsonResponse
    {
        // KML-0047: dopuszczamy datetime (Y-m-d H:i lub ISO) i fallback na date.
        // Walidacja akceptuje oba formaty - Carbon parsuje natywnie.
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'motorcycle_id' => 'nullable|string|uuid',
            'pickup_date' => 'required|date|after_or_equal:today',
            'return_date' => 'required|date|after:pickup_date',
            'notes' => 'nullable|string|max:1000',
            'rodo_consent' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $tenantId = $this->getTenantId($request);
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant) {
            app()->instance('current_tenant', $tenant);
        }

        // Find site for reservation
        $site = Site::first();

        $reservation = Reservation::create([
            'site_id' => $site?->id,
            'motorcycle_id' => $validated['motorcycle_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'pickup_date' => $validated['pickup_date'],
            'return_date' => $validated['return_date'],
            'notes' => $validated['notes'] ?? null,
            'rodo_consent' => true,
            'rodo_consent_at' => now(),
            'status' => Reservation::STATUS_PENDING,
        ]);

        // Send email notification
        $setting = SiteSetting::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->first();

        $notificationEmail = $setting?->reservation_notification_email;
        if ($notificationEmail) {
            try {
                $motorcycleId = $validated['motorcycle_id'] ?? null;
                $motorcycle = $motorcycleId
                    ? Motorcycle::withoutGlobalScope(TenantScope::class)->find($motorcycleId)
                    : null;

                Mail::to($notificationEmail)->send(new ReservationNotification(
                    reservation: $reservation,
                    motorcycleName: $motorcycle?->name,
                ));
            } catch (Exception $e) {
                Log::error('Failed to send reservation notification: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rezerwacja została przyjęta. Skontaktujemy się wkrótce.',
            'data' => [
                'id' => $reservation->id,
                'status' => $reservation->status,
                'pickup_date' => $reservation->pickup_date?->toDateString(),
                'return_date' => $reservation->return_date?->toDateString(),
            ],
        ], 201);
    }

    /**
     * Pobiera ID tenanta z requestu (header, query param, lub domyślny).
     * Dla super_admina bez podanego tenant_id zwraca null = "wszystkie tenanty".
     */
    private function getTenantId(Request $request): ?string
    {
        // 1. Z header
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId) {
            return $tenantId;
        }

        // 2. Z query param
        $tenantId = $request->query('tenant_id');
        if ($tenantId) {
            return $tenantId;
        }

        // 3. Administrator bez tenant_id → wszystkie tenanty (kontent klientów)
        if ($this->isAdminRequest($request)) {
            return null;
        }

        // 4. Z kontenera (jeśli ustawiony przez middleware)
        if (app()->bound('current_tenant')) {
            $tenant = app('current_tenant');
            if ($tenant) {
                return $tenant->id;
            }
        }

        // 5. Fallback: tenant demo-studio (dla publicznego API MotoRent)
        try {
            $tenant = Tenant::where('slug', 'demo-studio')
                ->where('is_active', true)
                ->first();

            if ($tenant) {
                return $tenant->id;
            }

            $tenant = Tenant::where('is_active', true)->first();

            return $tenant?->id;
        } catch (Exception $e) {
            return null;
        }
    }
}
