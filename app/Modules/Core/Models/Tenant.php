<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model reprezentujący tenanta (klienta) w systemie multi-tenancy.
 *
 * @property string $id UUID tenanta
 * @property string $name Nazwa tenanta
 * @property string $slug Unikalny identyfikator URL
 * @property string|null $domain Opcjonalna własna domena
 * @property string $plan Plan subskrypcji (starter, pro, enterprise)
 * @property string $database_type Typ bazy danych (shared, dedicated)
 * @property string|null $database_name Nazwa dedykowanej bazy (dla enterprise)
 * @property array<string, mixed> $settings Ustawienia tenanta w formacie JSON
 * @property bool $is_active Czy tenant jest aktywny
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TenantFeatureAccess> $featureAccess
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 *
 * @use HasFactory<\Database\Factories\TenantFactory>
 */
final class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): \Database\Factories\TenantFactory
    {
        return \Database\Factories\TenantFactory::new();
    }

    /**
     * Nazwa tabeli w bazie danych.
     */
    protected $table = 'tenants';

    /**
     * Atrybuty, które można masowo przypisywać.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'plan',
        'database_type',
        'database_name',
        'settings',
        'is_active',
    ];

    /**
     * Atrybuty, które są ukryte podczas serializacji.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'database_name',
    ];

    /**
     * Domyślne wartości atrybutów.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'plan' => 'starter',
        'database_type' => 'shared',
        'is_active' => true,
        'settings' => '{}',
    ];

    /**
     * Rzutowanie atrybutów na typy PHP.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Dostępne plany subskrypcji.
     */
    public const PLANS = [
        'starter' => 'Starter',
        'pro' => 'Pro',
        'enterprise' => 'Enterprise',
    ];

    /**
     * Typy baz danych.
     */
    public const DATABASE_TYPES = [
        'shared' => 'Współdzielona',
        'dedicated' => 'Dedykowana',
    ];

    /**
     * UUID specjalnego tenanta systemowego (dla super adminów).
     */
    public const SYSTEM_TENANT_ID = '00000000-0000-0000-0000-000000000000';

    /**
     * Slug specjalnego tenanta systemowego.
     */
    public const SYSTEM_TENANT_SLUG = 'system';

    /**
     * Sprawdza czy tenant jest systemowym tenantem (dla super adminów).
     */
    public function isSystemTenant(): bool
    {
        return $this->id === self::SYSTEM_TENANT_ID || $this->slug === self::SYSTEM_TENANT_SLUG;
    }

    /**
     * Pobiera systemowego tenanta (dla super adminów).
     */
    public static function getSystemTenant(): ?self
    {
        return self::where('id', self::SYSTEM_TENANT_ID)
            ->orWhere('slug', self::SYSTEM_TENANT_SLUG)
            ->first();
    }

    /**
     * Relacja: Tenant ma wielu użytkowników.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relacja: Tenant ma wiele dostępów do funkcjonalności.
     *
     * @return HasMany<TenantFeatureAccess, $this>
     */
    public function featureAccess(): HasMany
    {
        return $this->hasMany(TenantFeatureAccess::class);
    }

    /**
     * Sprawdza czy tenant ma dostęp do funkcjonalności.
     *
     * @param string $feature Nazwa funkcjonalności
     * @param string $action Akcja: view, create, edit, delete
     */
    public function hasFeatureAccess(string $feature, string $action = 'view'): bool
    {
        return TenantFeatureAccess::hasAccess($this->id, $feature, $action);
    }

    /**
     * Sprawdza czy tenant ma plan enterprise.
     */
    public function isEnterprise(): bool
    {
        return $this->plan === 'enterprise';
    }

    /**
     * Sprawdza czy tenant używa dedykowanej bazy danych.
     */
    public function hasDedicatedDatabase(): bool
    {
        return $this->database_type === 'dedicated' && $this->database_name !== null;
    }

    /**
     * Pobiera ustawienie z JSON.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Ustawia wartość ustawienia w JSON.
     *
     * @param  mixed  $value
     */
    public function setSetting(string $key, $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;

        return $this;
    }
}
