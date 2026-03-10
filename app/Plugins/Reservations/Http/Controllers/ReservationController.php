<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ReservationNotification;
use App\Models\Site;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use App\Modules\Content\Models\TwoWheels\SiteSetting;
use App\Modules\Core\Scopes\TenantScope;
use App\Plugins\Reservations\Http\Requests\StoreReservationRequest;
use App\Plugins\Reservations\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Controller API dla rezerwacji.
 *
 * Obsługuje endpoint POST /api/v1/sites/{site}/plugins/reservations
 */
class ReservationController extends Controller
{
    /**
     * Lista rezerwacji dla strony (opcjonalnie, dla testów).
     *
     * GET /api/v1/sites/{siteSlug}/plugins/reservations
     *
     * @param Request $request
     * @param string $siteSlug
     * @return JsonResponse
     */
    public function index(Request $request, string $siteSlug): JsonResponse
    {
        $site = Site::where('slug', $siteSlug)->first();

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Strona nie znaleziona.',
            ], 404);
        }

        $reservations = Reservation::forSite($site->id)
            ->upcoming()
            ->orderBy('pickup_date')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $reservations->items(),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
                'last_page' => $reservations->lastPage(),
            ],
        ]);
    }

    /**
     * Utworzenie nowej rezerwacji (z formularza na stronie).
     *
     * POST /api/v1/sites/{siteSlug}/plugins/reservations
     *
     * @param StoreReservationRequest $request
     * @param string $siteSlug
     * @return JsonResponse
     */
    public function store(StoreReservationRequest $request, string $siteSlug): JsonResponse
    {
        // Znajdujemy site po slug
        $site = Site::where('slug', $siteSlug)->first();

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Strona nie znaleziona.',
            ], 404);
        }

        $validated = $request->validated();

        // Pobieramy tenant - Site nie ma bezpośredniej relacji do Tenant,
        // więc używamy domyślnego tenanta (demo-studio) dla MVP
        $tenant = \App\Modules\Core\Models\Tenant::where('slug', 'demo-studio')
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd konfiguracji systemu. Skontaktuj się z administratorem.',
            ], 500);
        }

        // Bindujemy tenant do aplikacji (dla BelongsToTenant)
        app()->instance('current_tenant', $tenant);

        // Tworzymy rezerwację
        $reservation = Reservation::create([
            'site_id' => $site->id,
            'motorcycle_id' => $validated['motorcycle_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'pickup_date' => $validated['pickup_date'],
            'return_date' => $validated['return_date'],
            'notes' => $validated['notes'] ?? null,
            'rodo_consent' => $validated['rodo_consent'],
            'rodo_consent_at' => $validated['rodo_consent'] ? now() : null,
            'status' => Reservation::STATUS_PENDING,
        ]);

        Log::info('Nowa rezerwacja', [
            'reservation_id' => $reservation->id,
            'site_id' => $site->id,
            'customer_email' => $reservation->customer_email,
            'pickup_date' => $reservation->pickup_date?->toDateString(),
        ]);

        // Wysyłka powiadomienia email do admina
        $setting = SiteSetting::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
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
            } catch (\Exception $e) {
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
     * Pobranie szczegółów rezerwacji.
     *
     * GET /api/v1/sites/{siteSlug}/plugins/reservations/{reservation}
     *
     * @param string $siteSlug
     * @param string $reservationId
     * @return JsonResponse
     */
    public function show(string $siteSlug, string $reservationId): JsonResponse
    {
        $site = Site::where('slug', $siteSlug)->first();

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Strona nie znaleziona.',
            ], 404);
        }

        $reservation = Reservation::find($reservationId);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Rezerwacja nie znaleziona.',
            ], 404);
        }

        // Sprawdź czy rezerwacja należy do tej strony
        if ($reservation->site_id !== $site->id) {
            return response()->json([
                'success' => false,
                'message' => 'Rezerwacja nie znaleziona.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $reservation->id,
                'motorcycle' => $reservation->motorcycle_id,
                'customer_name' => $reservation->customer_name,
                'pickup_date' => $reservation->pickup_date?->toDateString(),
                'return_date' => $reservation->return_date?->toDateString(),
                'status' => $reservation->status,
                'status_label' => $reservation->status_label,
                'days' => $reservation->days,
                'total_price' => $reservation->total_price,
                'notes' => $reservation->notes,
            ],
        ]);
    }
}
