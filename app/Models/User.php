<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Core\Models\Tenant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Model użytkownika systemu.
 *
 * UWAGA: Ten model celowo NIE używa traitu BelongsToTenant, ponieważ:
 * 1. Super admini mają tenant_id = system tenant (Tenant 0)
 * 2. Filament HasTenants implementuje własną logikę multi-tenancy
 * 3. Administratorzy muszą widzieć użytkowników wszystkich tenantów
 *
 * Do filtrowania użytkowników po tenancie użyj: User::forTenant($tenant)->get()
 *
 * @property string $id UUID użytkownika
 * @property string $name Imię i nazwisko
 * @property string $email Adres email
 * @property string|null $tenant_id UUID tenanta (system tenant dla super_admin)
 * @property bool $is_super_admin Czy jest super administratorem
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password Zahashowane hasło
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static static create(array<string, mixed> $attributes = [])
 */
class User extends Authenticatable implements FilamentUser, HasTenants, HasAvatar, MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use Notifiable;

    /**
     * Atrybuty, które można masowo przypisywać.
     *
     * BEZPIECZEŃSTWO:
     * - is_super_admin NIE jest tutaj - chronione przed privilege escalation
     * - tenant_id NIE jest tutaj - zapobiega przypisaniu do obcego tenanta
     *
     * Aby przypisać użytkownika do tenanta, użyj:
     * $user->tenant_id = $tenant->id;
     * $user->save();
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'theme_colors',
        'wallpaper_url',
        'panel_preferences',
    ];

    /**
     * Atrybuty ukryte podczas serializacji.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Domyślne wartości atrybutów.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_super_admin' => false,
    ];

    /**
     * Rzutowanie atrybutów na typy PHP.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'theme_colors' => 'array',
            'panel_preferences' => 'array',
        ];
    }

    /**
     * Relacja: Użytkownik należy do tenanta.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relacja: Custom navigation items użytkownika.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<UserCustomNavigationItem>
     */
    public function customNavigationItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserCustomNavigationItem::class)->orderBy('sort_order');
    }

    /**
     * Pobiera URL avatara użytkownika dla Filament.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return Storage::disk('public')->url($this->avatar_url);
        }
        return null;
    }

    /**
     * Pobiera URL avatara użytkownika (alias dla kompatybilności).
     */
    public function getAvatarUrl(): ?string
    {
        return $this->getFilamentAvatarUrl();
    }

    /**
     * Sprawdza czy użytkownik ma dostęp do panelu Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Super admin ma dostęp do wszystkiego
        if ($this->is_super_admin) {
            return true;
        }

        // Zwykły użytkownik musi mieć przypisanego tenanta
        $tenantId = $this->tenant_id;
        // Normalizuj jeśli jest tablicą
        if (is_array($tenantId)) {
            $tenantId = !empty($tenantId) ? (string) reset($tenantId) : null;
        }

        if ($tenantId === null) {
            return false;
        }

        // Sprawdź czy to nie jest system tenant (powinien być tylko dla super adminów)
        if ($this->tenant && $this->tenant->isSystemTenant() && !$this->is_super_admin) {
            return false;
        }

        // Użytkownik musi mieć zweryfikowany email
        if (! $this->hasVerifiedEmail()) {
            return false;
        }

        // Sprawdź czy tenant jest aktywny
        return $this->tenant !== null && $this->tenant->is_active;
    }

    /**
     * Pobiera tenanty, do których użytkownik ma dostęp (dla Filament multi-tenancy).
     *
     * @return Collection<int, Model>
     */
    public function getTenants(Panel $panel): Collection
    {
        // Super admin widzi wszystkich aktywnych tenantów (oprócz system tenant)
        if ($this->is_super_admin) {
            return Tenant::where('is_active', true)
                ->where('slug', '!=', Tenant::SYSTEM_TENANT_SLUG)
                ->get();
        }

        // Zwykły użytkownik widzi tylko swojego tenanta (jeśli aktywny i nie jest system tenant)
        if ($this->tenant !== null && $this->tenant->is_active && !$this->tenant->isSystemTenant()) {
            return collect([$this->tenant]);
        }

        return collect();
    }

    /**
     * Sprawdza czy użytkownik może uzyskać dostęp do danego tenanta.
     *
     * BEZPIECZEŃSTWO: Metoda wymaga instancji Tenant i zawsze weryfikuje is_active.
     * Super admin ma dostęp tylko do AKTYWNYCH tenantów.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        // Model MUSI być instancją Tenant - fail-closed dla innych typów
        if (! $tenant instanceof Tenant) {
            return false;
        }

        // Tenant MUSI być aktywny - dotyczy WSZYSTKICH użytkowników włącznie z super adminem
        if (! $tenant->is_active) {
            return false;
        }

        // Super admin ma dostęp do wszystkich aktywnych tenantów
        if ($this->is_super_admin) {
            return true;
        }

        // Sprawdź czy to tenant użytkownika
        return $this->tenant_id === $tenant->getKey();
    }

    /**
     * Sprawdza czy użytkownik jest super administratorem.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin || $this->hasRole('super_admin');
    }

    /**
     * Sprawdza czy użytkownik jest administratorem swojego tenanta.
     */
    public function isTenantAdmin(): bool
    {
        return $this->hasRole('tenant_admin');
    }

    /**
     * Scope: Filtruj użytkowników po tenancie.
     *
     * Używaj tego scope do ręcznego filtrowania użytkowników:
     * User::forTenant($tenant)->get()
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForTenant($query, Tenant|string $tenant)
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Tylko użytkownicy z przypisanym tenantem (bez super adminów).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithTenant(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \Illuminate\Database\Eloquent\Builder<static> */
        return $query->whereNotNull('tenant_id');
    }

    /**
     * Scope: Tylko super admini.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

}
