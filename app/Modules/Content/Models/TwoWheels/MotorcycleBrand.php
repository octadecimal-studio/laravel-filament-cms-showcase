<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Content\Models\Media;
use App\Modules\Core\Scopes\TenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Model marki motocykli.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $name Nazwa marki
 * @property string $slug Slug (unique)
 * @property string|null $description Opis
 * @property string|null $logo_id UUID logo (Media)
 * @property bool $published Czy opublikowane
 * @property Carbon|null $published_at Data publikacji
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Media|null $logo
 * @property-read Collection<int, Motorcycle> $motorcycles
 */
final class MotorcycleBrand extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\MotorcycleBrandFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'two_wheels_motorcycle_brands';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_id',
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

        static::creating(function (MotorcycleBrand $brand): void {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    /**
     * Relacja: Logo (Media).
     *
     * @return BelongsTo<Media, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_id')
            ->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Relacja: Motocykle tej marki.
     *
     * @return HasMany<Motorcycle, $this>
     */
    public function motorcycles(): HasMany
    {
        return $this->hasMany(Motorcycle::class, 'brand_id')
            ->withoutGlobalScope(TenantScope::class);
    }
}
