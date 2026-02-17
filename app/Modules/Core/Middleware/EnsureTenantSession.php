<?php

declare(strict_types=1);

namespace App\Modules\Core\Middleware;

use App\Modules\Core\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ustawiający kontekst tenanta w sesji i kontenerze aplikacji.
 *
 * Sprawdza i waliduje tenanta na podstawie:
 * 1. Zalogowanego użytkownika (walidacja przynależności)
 * 2. Parametru w URL (dla super_admin)
 * 3. Domeny (dla enterprise z własną domeną) - działa też dla niezalogowanych
 *
 * BEZPIECZEŃSTWO:
 * - Tenant z sesji jest walidowany względem zalogowanego użytkownika
 * - Niezalogowani użytkownicy mogą uzyskać kontekst tylko przez domenę
 * - Super admin może przełączać się między tenantami przez URL
 */
final class EnsureTenantSession
{
    /**
     * Obsługuje przychodzące żądanie.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        // Walidacja dostępu
        if ($tenant === null) {
            // Niezalogowany użytkownik bez kontekstu domeny - OK (np. strona logowania)
            if ($user === null) {
                return $next($request);
            }

            // Zalogowany użytkownik bez tenanta
            if (! $this->isSuperAdmin($user)) {
                // Zwykły użytkownik bez tenanta - błąd
                abort(404);
            }
            // Super admin bez wybranego tenanta - OK
        }

        if ($tenant !== null) {
            // Sprawdź czy tenant jest aktywny
            if (! $tenant->is_active) {
                abort(404, 'Konto zostało dezaktywowane.');
            }

            // Waliduj że zalogowany użytkownik ma dostęp do tego tenanta
            if ($user !== null && ! $this->userCanAccessTenant($user, $tenant)) {
                abort(404);
            }

            // Ustaw tenant w kontenerze i sesji
            $this->setCurrentTenant($tenant);
        }

        return $next($request);
    }

    /**
     * Próbuje rozpoznać tenanta na podstawie różnych źródeł.
     *
     * @param  mixed  $user
     */
    private function resolveTenant(Request $request, $user): ?Tenant
    {
        // 1. Sprawdź domenę (dla enterprise) - działa też dla niezalogowanych
        // To musi być pierwsze, bo pozwala na kontekst przed logowaniem
        $host = $request->getHost();
        $domainTenant = Tenant::where('domain', $host)
            ->where('is_active', true)
            ->first();
        if ($domainTenant !== null) {
            return $domainTenant;
        }

        // 2. Sprawdź parametr tenant w URL (dla super_admin)
        $tenantSlug = $request->route('tenant');
        if ($tenantSlug !== null && $this->isSuperAdmin($user)) {
            return Tenant::where('slug', $tenantSlug)
                ->where('is_active', true)
                ->first();
        }

        // 3. Sprawdź czy użytkownik jest zalogowany i ma przypisanego tenanta
        if ($user !== null && isset($user->tenant_id) && $user->tenant_id !== '') {
            $tenant = Tenant::where('id', $user->tenant_id)
                ->where('is_active', true)
                ->first();

            // Jeśli to system tenant i użytkownik jest super adminem, zwróć null
            // (super admin nie potrzebuje wybranego tenanta w sesji - widzi wszystkich)
            if ($tenant && $tenant->isSystemTenant() && $this->isSuperAdmin($user)) {
                return null; // Super admin bez wybranego tenanta = widzi wszystkich
            }

            return $tenant;
        }

        // 4. Fallback: pobierz z sesji (tylko jeśli użytkownik jest zalogowany i walidacja przejdzie)
        if ($user !== null) {
            $tenantId = session('tenant_id');
            
            // Normalizuj tenant_id jeśli jest tablicą
            if (is_array($tenantId)) {
                $tenantId = !empty($tenantId) ? (string) reset($tenantId) : null;
            } elseif ($tenantId !== null) {
                $tenantId = (string) $tenantId;
            }
            
            if ($tenantId !== null && $tenantId !== '') {
                $tenant = Tenant::where('id', $tenantId)
                    ->where('is_active', true)
                    ->first();

                // Waliduj że użytkownik ma dostęp do tego tenanta
                if ($tenant !== null && $this->userCanAccessTenant($user, $tenant)) {
                    return $tenant;
                }
            }
        }

        return null;
    }

    /**
     * Sprawdza czy użytkownik ma dostęp do tenanta.
     *
     * @param  mixed  $user
     */
    private function userCanAccessTenant($user, Tenant $tenant): bool
    {
        // Super admin ma dostęp do wszystkich tenantów
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // Zwykły użytkownik - tylko do swojego tenanta
        return isset($user->tenant_id) && $user->tenant_id === $tenant->id;
    }

    /**
     * Ustawia aktualnego tenanta w kontenerze i sesji.
     */
    private function setCurrentTenant(Tenant $tenant): void
    {
        // Ustaw w kontenerze aplikacji
        app()->instance('current_tenant', $tenant);

        // Ustaw w sesji - upewnij się, że tenant_id to string (nie tablica)
        $tenantId = $tenant->id;
        if (is_array($tenantId)) {
            // Jeśli to tablica, wyciągnij pierwszy element lub zserializuj
            $tenantId = !empty($tenantId) ? (string) reset($tenantId) : null;
        } else {
            $tenantId = (string) $tenantId;
        }

        if ($tenantId !== null) {
            session(['tenant_id' => $tenantId]);
        }
    }

    /**
     * Sprawdza czy użytkownik jest super adminem.
     *
     * @param  mixed  $user
     */
    private function isSuperAdmin($user): bool
    {
        if ($user === null) {
            return false;
        }

        // Sprawdź atrybut is_super_admin
        if (isset($user->is_super_admin) && $user->is_super_admin === true) {
            return true;
        }

        // Sprawdź rolę super_admin (Spatie Permission)
        if (is_object($user) && method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return false;
    }
}
