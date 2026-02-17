<?php

declare(strict_types=1);

namespace App\Modules\Core\Traits;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait zapewniający multi-tenancy dla modeli.
 *
 * Każdy model używający tego traitu automatycznie:
 * - Jest filtrowany przez TenantScope (izolacja danych)
 * - Ma ustawiany tenant_id przy tworzeniu
 * - Ma zablokowaną zmianę tenant_id przez mass assignment
 *
 * UWAGA: Trait jest aktualnie unused - przygotowany na przyszłość.
 * Zostanie użyty w Content/Media modelach (Faza 2).
 *
 * @property string $tenant_id UUID tenanta
 * @property-read Tenant|null $tenant
 *
 * @mixin Model
 *
 * @phpstan-ignore-next-line trait.unused
 */
trait BelongsToTenant
{
    /**
     * Boot traitu - rejestruje Global Scope i eventy.
     */
    public static function bootBelongsToTenant(): void
    {
        // Dodaj Global Scope filtrujący po tenant_id
        static::addGlobalScope(new TenantScope);

        // Automatycznie ustaw tenant_id przy tworzeniu
        static::creating(function (Model $model): void {
            /** @phpstan-ignore-next-line property.notFound */
            if (empty($model->tenant_id)) {
                /** @phpstan-ignore-next-line property.notFound */
                $model->tenant_id = static::getCurrentTenantId();
            }
        });

        // Zablokuj zmianę tenant_id przy aktualizacji (bezpieczeństwo)
        static::updating(function (Model $model): void {
            /** @phpstan-ignore-next-line property.notFound */
            if ($model->isDirty('tenant_id')) {
                // Przywróć oryginalną wartość - zmiana tenant_id jest niedozwolona
                /** @phpstan-ignore-next-line property.notFound */
                $model->tenant_id = $model->getOriginal('tenant_id');
            }
        });
    }

    /**
     * Inicjalizacja traitu - dodaje tenant_id do guarded.
     */
    public function initializeBelongsToTenant(): void
    {
        // Dodaj tenant_id do guarded, żeby zapobiec mass assignment
        // PHPStan: $this->guarded może być bool lub array, sprawdzamy typ
        $guarded = $this->guarded;
        if (is_array($guarded) && ! in_array('tenant_id', $guarded, true)) {
            // Zamiast $this->guarded[] = 'tenant_id' (problem z PHPStan)
            // używamy array_merge który zwraca array
            $this->guarded = array_merge($guarded, ['tenant_id']);
        }
    }

    /**
     * Relacja: Model należy do Tenanta.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Zapytanie dla konkretnego tenanta (bez global scope).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForTenant($query, Tenant|string $tenant)
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId);
    }

    /**
     * Pobiera ID aktualnego tenanta z kontekstu.
     */
    protected static function getCurrentTenantId(): ?string
    {
        // Najpierw sprawdź kontener aplikacji
        if (app()->bound('current_tenant')) {
            $tenant = app('current_tenant');

            return $tenant instanceof Tenant ? $tenant->id : null;
        }

        // Fallback: sesja
        $tenantId = session('tenant_id');
        if ($tenantId !== null) {
            return (string) $tenantId;
        }

        // Fallback: zalogowany użytkownik
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if ($user !== null && isset($user->tenant_id)) {
            return $user->tenant_id;
        }

        return null;
    }

    /**
     * Sprawdza czy model należy do podanego tenanta.
     */
    public function belongsToTenant(Tenant|string $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->tenant_id === $tenantId;
    }
}
