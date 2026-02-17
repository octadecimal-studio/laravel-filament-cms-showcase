<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model reprezentujący dostęp tenanta do funkcjonalności.
 *
 * Przechowuje granularne uprawnienia (CRUD) dla każdej funkcjonalności
 * przypisanej do tenanta. Umożliwia elastyczne zarządzanie dostępami
 * klientów do poszczególnych modułów systemu.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $feature Nazwa funkcjonalności (np. motorcycles, reservations)
 * @property string|null $feature_group Grupa funkcjonalności dla UI
 * @property bool $can_view Czy może przeglądać
 * @property bool $can_create Czy może tworzyć
 * @property bool $can_edit Czy może edytować
 * @property bool $can_delete Czy może usuwać
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
final class TenantFeatureAccess extends Model
{
    use HasUuids;

    /**
     * Nazwa tabeli w bazie danych.
     */
    protected $table = 'tenant_feature_access';

    /**
     * Atrybuty, które można masowo przypisywać.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'feature',
        'feature_group',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
    ];

    /**
     * Rzutowanie atrybutów na typy PHP.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_edit' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    /**
     * Domyślne wartości atrybutów.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'can_view' => false,
        'can_create' => false,
        'can_edit' => false,
        'can_delete' => false,
    ];

    /**
     * Dostępne funkcjonalności z ich grupami.
     * Klucz = nazwa feature, wartość = [grupa, etykieta PL].
     *
     * @var array<string, array{group: string, label: string}>
     */
    public const FEATURES = [
        // MotoRent Demo
        'motorcycles' => ['group' => 'motorent_demo', 'label' => 'Motocykle'],
        'motorcycle_brands' => ['group' => 'motorent_demo', 'label' => 'Marki motocykli'],
        'motorcycle_categories' => ['group' => 'motorent_demo', 'label' => 'Kategorie motocykli'],
        'testimonials' => ['group' => 'motorent_demo', 'label' => 'Opinie klientów'],
        'features' => ['group' => 'motorent_demo', 'label' => 'Zalety wypożyczalni'],
        'process_steps' => ['group' => 'motorent_demo', 'label' => 'Kroki procesu'],
        'site_settings' => ['group' => 'motorent_demo', 'label' => 'Ustawienia strony'],
        'gallery' => ['group' => 'motorent_demo', 'label' => 'Galeria zdjęć'],

        // Plugins
        'reservations' => ['group' => 'plugins', 'label' => 'Rezerwacje'],

        // CRM
        'sites' => ['group' => 'crm', 'label' => 'Strony'],
        'corrections' => ['group' => 'crm', 'label' => 'Poprawki'],
    ];

    /**
     * Grupy funkcjonalności z etykietami.
     *
     * @var array<string, string>
     */
    public const FEATURE_GROUPS = [
        'motorent_demo' => 'MotoRent Demo',
        'plugins' => 'Plugins',
        'crm' => 'CRM',
    ];

    /**
     * Relacja: Dostęp należy do tenanta.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Sprawdza czy tenant ma dostęp do danej funkcjonalności z określoną akcją.
     *
     * @param string $tenantId UUID tenanta
     * @param string $feature Nazwa funkcjonalności
     * @param string $action Akcja: view, create, edit, delete
     */
    public static function hasAccess(string $tenantId, string $feature, string $action = 'view'): bool
    {
        $column = 'can_' . $action;

        $access = static::where('tenant_id', $tenantId)
            ->where('feature', $feature)
            ->first();

        if ($access === null) {
            return false;
        }

        return (bool) $access->{$column};
    }

    /**
     * Pobiera wszystkie dostępy dla tenanta pogrupowane.
     *
     * @param string $tenantId UUID tenanta
     * @return array<string, array<string, array{can_view: bool, can_create: bool, can_edit: bool, can_delete: bool}>>
     */
    public static function getGroupedAccessForTenant(string $tenantId): array
    {
        $accesses = static::where('tenant_id', $tenantId)->get();

        $grouped = [];
        foreach (self::FEATURE_GROUPS as $groupKey => $groupLabel) {
            $grouped[$groupKey] = [];
        }

        foreach ($accesses as $access) {
            $group = $access->feature_group ?? 'other';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][$access->feature] = [
                'can_view' => $access->can_view,
                'can_create' => $access->can_create,
                'can_edit' => $access->can_edit,
                'can_delete' => $access->can_delete,
            ];
        }

        return $grouped;
    }

    /**
     * Ustawia lub aktualizuje dostęp dla tenanta.
     *
     * @param string $tenantId UUID tenanta
     * @param string $feature Nazwa funkcjonalności
     * @param array{can_view?: bool, can_create?: bool, can_edit?: bool, can_delete?: bool} $permissions
     */
    public static function setAccess(string $tenantId, string $feature, array $permissions): self
    {
        $featureConfig = self::FEATURES[$feature] ?? null;
        $group = $featureConfig['group'] ?? null;

        return static::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'feature' => $feature,
            ],
            array_merge(
                ['feature_group' => $group],
                $permissions
            )
        );
    }
}
