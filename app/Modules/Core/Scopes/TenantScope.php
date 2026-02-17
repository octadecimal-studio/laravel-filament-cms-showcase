<?php

declare(strict_types=1);

namespace App\Modules\Core\Scopes;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global Scope filtrujący wszystkie zapytania po tenant_id.
 *
 * Ten scope jest automatycznie stosowany do wszystkich modeli
 * używających traitu BelongsToTenant. Zapewnia izolację danych
 * między tenantami.
 *
 * BEZPIECZEŃSTWO:
 * - Scope waliduje, że sesyjny tenant_id należy do zalogowanego użytkownika
 * - Gdy brak kontekstu tenanta, stosuje FAIL-CLOSED - zwraca puste wyniki
 * - Aby pominąć filtrowanie, użyj: ->withoutGlobalScope(TenantScope::class)
 */
final class TenantScope implements Scope
{
    /**
     * Stosuje scope do zapytania Eloquent.
     *
     * BEZPIECZEŃSTWO: Gdy brak kontekstu tenanta, scope stosuje warunek
     * który NIE zwróci żadnych rekordów (fail-closed). Zapobiega to
     * wyciekowi danych między tenantami w kontekstach bez tenant binding
     * (np. komendy artisan, queue jobs).
     *
     * Aby wykonać zapytanie bez filtrowania po tenancie (np. dla super admina),
     * użyj: Model::withoutGlobalScope(TenantScope::class)->get()
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = $this->getCurrentTenant();

        if ($tenant !== null) {
            // Filtruj po aktywnym tenancie
            $builder->where($model->getTable().'.tenant_id', $tenant->id);
        } else {
            // Sprawdź czy użytkownik jest super adminem (z system tenant)
            // Super admin bez wybranego tenanta widzi wszystkie dane
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if ($user !== null && ($user->is_super_admin || $user->hasRole('super_admin'))) {
                // Sprawdź czy użytkownik ma system tenant
                if ($user->tenant_id === \App\Modules\Core\Models\Tenant::SYSTEM_TENANT_ID) {
                    // Super admin widzi wszystkie dane (bez filtrowania)
                    return;
                }
            }

            // FAIL-CLOSED: Brak kontekstu tenanta = brak wyników
            // Zapobiega przypadkowemu zwróceniu wszystkich rekordów
            // Używamy WHERE 1=0 jako niemożliwy warunek
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * Pobiera aktualnego tenanta z kontekstu.
     *
     * Priorytet:
     * 1. Kontener aplikacji (ustawiony przez middleware)
     * 2. Zalogowany użytkownik (z walidacją)
     *
     * UWAGA: Sesja NIE jest używana bezpośrednio - tylko przez middleware,
     * który waliduje przynależność użytkownika do tenanta.
     */
    private function getCurrentTenant(): ?Tenant
    {
        // Sprawdzamy czy jest ustawiony tenant w kontekście aplikacji
        // (ustawiony przez EnsureTenantSession middleware po walidacji)
        if (app()->bound('current_tenant')) {
            $tenant = app('current_tenant');

            // Dodatkowa walidacja: sprawdź czy tenant jest aktywny
            if ($tenant instanceof Tenant && $tenant->is_active) {
                return $tenant;
            }

            return null;
        }

        // Fallback: pobierz od zalogowanego użytkownika
        // NIE używamy sesji bezpośrednio - to mogłoby prowadzić do manipulacji
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if ($user !== null && isset($user->tenant_id) && $user->tenant_id !== '') {
            // Normalizuj tenant_id jeśli jest tablicą
            $tenantId = $user->tenant_id;
            if (is_array($tenantId)) {
                $tenantId = !empty($tenantId) ? (string) reset($tenantId) : null;
            } else {
                $tenantId = (string) $tenantId;
            }

            if ($tenantId !== null && $tenantId !== '') {
                // Waliduj że tenant istnieje i jest aktywny
                $tenant = Tenant::where('id', $tenantId)
                    ->where('is_active', true)
                    ->first();

                return $tenant;
            }
        }

        return null;
    }
}
