<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Http\Controllers;

use App\Modules\Core\Models\Tenant;
use Exception;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Octadecimal\Rental\Models\Rental;

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
        $tenant = Tenant::where('slug', 'demo-studio')
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

        // KML-0053: dodatkowo tworzymy wpis w "rentals" (vendor pkg
        // octadecimalhq/reservation-system) — admin widzi rezerwacje
        // z formularza telefonicznego razem z rezerwacjami online
        // w /admin/rentals oraz na widgecie kalendarza.
        try {
            $motorcycleId = $validated['motorcycle_id'] ?? null;
            $motorcycle = $motorcycleId
                ? Motorcycle::withoutGlobalScope(TenantScope::class)->find($motorcycleId)
                : null;

            $pickupDate = $reservation->pickup_date?->toDateString();
            $returnDate = $reservation->return_date?->toDateString();

            if ($motorcycle && $pickupDate && $returnDate) {
                $startAt = Carbon::parse($pickupDate.' 10:00:00');
                $endAt = Carbon::parse($returnDate.' 10:00:00');

                Rental::create([
                    'rentable_type' => Motorcycle::class,
                    'rentable_id' => $motorcycle->getKey(),
                    'name' => $reservation->customer_name,
                    'email' => $reservation->customer_email,
                    'phone' => $reservation->customer_phone,
                    'start_date' => $pickupDate,
                    'end_date' => $returnDate,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'qty' => 1,
                    'total_amount' => 0, // wycena ustalana telefonicznie przez biuro
                    'paid_amount' => 0,
                    'currency' => 'PLN',
                    'status' => 'pending',
                    'gdpr_consent' => (bool) $reservation->rodo_consent,
                    'meta' => [
                        'source' => 'phone_form',
                        'reservation_id' => (string) $reservation->id,
                        'notes' => $reservation->notes,
                    ],
                ]);
            } else {
                Log::warning('KML-0053: nie utworzono Rental — brak motocykla lub dat', [
                    'reservation_id' => $reservation->id,
                    'has_motorcycle' => (bool) $motorcycle,
                    'pickup_date' => $pickupDate,
                    'return_date' => $returnDate,
                ]);
            }
        } catch (Exception $e) {
            Log::error('KML-0053: blad tworzenia Rental ze starego formularza: '.$e->getMessage(), [
                'reservation_id' => $reservation->id,
            ]);
        }

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
