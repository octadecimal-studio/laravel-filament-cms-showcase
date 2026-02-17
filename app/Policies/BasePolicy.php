<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

/**
 * Bazowa klasa Policy z obsługą multi-tenancy.
 *
 * Każda policy dziedzicząca z tej klasy automatycznie:
 * 1. Sprawdza uprawnienia Spatie Permission
 * 2. Weryfikuje tenant isolation (użytkownik widzi tylko dane swojego tenanta)
 * 3. Daje super_admin pełny dostęp
 */
abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Prefix dla uprawnień (np. 'customers', 'sites').
     */
    protected string $permissionPrefix;

    /**
     * Before hook - super_admin ma dostęp do wszystkiego.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Czy użytkownik może wyświetlić listę?
     */
    public function viewAny(User $user): bool
    {
        return $user->can("{$this->permissionPrefix}.view_any");
    }

    /**
     * Czy użytkownik może zobaczyć konkretny rekord?
     */
    public function view(User $user, Model $model): bool
    {
        if (!$user->can("{$this->permissionPrefix}.view")) {
            return false;
        }

        return $this->belongsToUserTenant($user, $model);
    }

    /**
     * Czy użytkownik może tworzyć nowe rekordy?
     */
    public function create(User $user): bool
    {
        return $user->can("{$this->permissionPrefix}.create");
    }

    /**
     * Czy użytkownik może edytować rekord?
     */
    public function update(User $user, Model $model): bool
    {
        if (!$user->can("{$this->permissionPrefix}.update")) {
            return false;
        }

        return $this->belongsToUserTenant($user, $model);
    }

    /**
     * Czy użytkownik może usunąć rekord?
     */
    public function delete(User $user, Model $model): bool
    {
        if (!$user->can("{$this->permissionPrefix}.delete")) {
            return false;
        }

        return $this->belongsToUserTenant($user, $model);
    }

    /**
     * Czy użytkownik może przywrócić usunięty rekord?
     */
    public function restore(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Czy użytkownik może trwale usunąć rekord?
     */
    public function forceDelete(User $user, Model $model): bool
    {
        // Tylko super_admin może trwale usuwać
        return false;
    }

    /**
     * Sprawdza czy model należy do tenanta użytkownika.
     *
     * KRYTYCZNE dla izolacji danych!
     */
    protected function belongsToUserTenant(User $user, Model $model): bool
    {
        // Super admin widzi wszystko (obsłużone w before())
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Sprawdź czy model ma tenant_id
        if (!isset($model->tenant_id)) {
            // Model bez tenant_id - zezwól (np. globalne ustawienia)
            return true;
        }

        // Użytkownik musi mieć przypisanego tenanta
        $userTenantId = $user->tenant_id;
        // Normalizuj jeśli jest tablicą
        if (is_array($userTenantId)) {
            $userTenantId = !empty($userTenantId) ? (string) reset($userTenantId) : null;
        }

        if ($userTenantId === null) {
            return false;
        }

        // Sprawdź czy użytkownik ma system tenant (super admin)
        if ($userTenantId === \App\Modules\Core\Models\Tenant::SYSTEM_TENANT_ID) {
            // Super admin widzi wszystko
            return true;
        }

        // Tenant musi się zgadzać
        $modelTenantId = $model->tenant_id;
        // Normalizuj jeśli jest tablicą
        if (is_array($modelTenantId)) {
            $modelTenantId = !empty($modelTenantId) ? (string) reset($modelTenantId) : null;
        }

        return $modelTenantId === $userTenantId;
    }

    /**
     * Sprawdza czy model należy do site'a dostępnego dla użytkownika.
     *
     * Używaj dla modeli z relacją site (np. Motorcycle, Reservation).
     */
    protected function belongsToUserSite(User $user, Model $model): bool
    {
        // Najpierw sprawdź tenant
        if (!$this->belongsToUserTenant($user, $model)) {
            return false;
        }

        // Jeśli model ma site_id, sprawdź czy użytkownik ma dostęp do tego site
        if (isset($model->site_id) && $user->sites()->exists()) {
            return $user->sites()->where('sites.id', $model->site_id)->exists();
        }

        // Brak ograniczenia site-level
        return true;
    }
}
