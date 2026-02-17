<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Model motocykla.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $name Nazwa motocykla
 * @property string $slug Slug (unique)
 * @property string $brand_id UUID marki
 * @property string $category_id UUID kategorii
 * @property string $main_image_id UUID głównego obrazu (Media)
 * @property int $engine_capacity Pojemność silnika (cc)
 * @property int $year Rok produkcji
 * @property float $price_per_day Cena za dzień
 * @property float $price_per_week Cena za tydzień
 * @property float $price_per_month Cena za miesiąc
 * @property float $deposit Kaucja
 * @property string|null $description Opis (richtext)
 * @property array<string, mixed>|null $specifications Specyfikacje (JSON)
 * @property bool $available Dostępny
 * @property bool $featured Wyróżniony
 * @property bool $published Czy opublikowane
 * @property \Illuminate\Support\Carbon|null $published_at Data publikacji
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read MotorcycleBrand $brand
 * @property-read MotorcycleCategory $category
 * @property-read \App\Modules\Content\Models\Media $mainImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Content\Models\Media> $gallery
 */
final class Motorcycle extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\MotorcycleFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'two_wheels_motorcycles';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'brand_id',
        'category_id',
        'main_image_id',
        'engine_capacity',
        'year',
        'price_per_day',
        'price_per_week',
        'price_per_month',
        'deposit',
        'description',
        'specifications',
        'available',
        'featured',
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
            'engine_capacity' => 'integer',
            'year' => 'integer',
            'price_per_day' => 'decimal:2',
            'price_per_week' => 'decimal:2',
            'price_per_month' => 'decimal:2',
            'deposit' => 'decimal:2',
            'specifications' => 'array',
            'available' => 'boolean',
            'featured' => 'boolean',
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

        static::creating(function (Motorcycle $motorcycle): void {
            if (empty($motorcycle->slug)) {
                $motorcycle->slug = Str::slug($motorcycle->name);
            }
        });
    }

    /**
     * Relacja: Marka.
     *
     * @return BelongsTo<MotorcycleBrand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(MotorcycleBrand::class, 'brand_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }

    /**
     * Relacja: Kategoria.
     *
     * @return BelongsTo<MotorcycleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MotorcycleCategory::class, 'category_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }

    /**
     * Relacja: Główny obraz (opcjonalne).
     *
     * @return BelongsTo<\App\Modules\Content\Models\Media, $this>
     */
    public function mainImage(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Content\Models\Media::class, 'main_image_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }

    /**
     * Relacja: Galeria (przez pivot table).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Modules\Content\Models\Media>
     */
    public function gallery(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Content\Models\Media::class,
            'two_wheels_motorcycle_gallery',
            'motorcycle_id',
            'media_id'
        )->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class)
            ->withTimestamps()
            ->orderBy('two_wheels_motorcycle_gallery.order');
    }

    /**
     * Scope: Tylko opublikowane.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope: Tylko dostępne.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAvailable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('available', true);
    }

    /**
     * Scope: Wyróżnione.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeFeatured(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('featured', true);
    }
}
