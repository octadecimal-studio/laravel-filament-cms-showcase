<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Model kategorii motocykli.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $name Nazwa kategorii
 * @property string $slug Slug (unique)
 * @property string|null $description Opis
 * @property string $color Kolor (hex)
 * @property bool $published Czy opublikowane
 * @property \Illuminate\Support\Carbon|null $published_at Data publikacji
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Motorcycle> $motorcycles
 * @property-read \App\Modules\Content\Models\TwoWheels\PricingTier|null $pricingTier
 */
final class MotorcycleCategory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\MotorcycleCategoryFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'two_wheels_motorcycle_categories';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'published',
        'published_at',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Boot modelu - auto-generowanie slug.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MotorcycleCategory $category): void {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Relacja: Motocykle w tej kategorii.
     *
     * @return HasMany<Motorcycle, $this>
     */
    public function motorcycles(): HasMany
    {
        return $this->hasMany(Motorcycle::class, 'category_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }

    /**
     * Relacja: Pricing Tier dla kategorii.
     *
     * @return HasOne<PricingTier, $this>
     */
    public function pricingTier(): HasOne
    {
        return $this->hasOne(PricingTier::class, 'category_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }
}
