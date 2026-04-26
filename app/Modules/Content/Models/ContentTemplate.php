<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use Database\Factories\ContentTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model szablonów treści.
 *
 * Definiuje gotowe szablony stron, sekcji, emaili z predefiniowaną strukturą.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $name Nazwa szablonu
 * @property string $slug Slug (unikalny)
 * @property string|null $category Kategoria (page, section, email)
 * @property string|null $description Opis
 * @property array<string, mixed> $structure Struktura bloków i layoutu (JSON)
 * @property array<string, mixed>|null $default_data Domyślne dane
 * @property array<string, mixed>|null $config Konfiguracja (colors, fonts, spacing)
 * @property array<string, mixed>|null $preview Preview data
 * @property string|null $thumbnail_url URL miniaturki
 * @property array<string>|null $tags Tagi
 * @property bool $is_active Czy aktywny
 * @property bool $is_premium Czy premium
 * @property int $usage_count Licznik użyć
 * @property float|null $rating Ocena (0.00-5.00)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Builder<static> query()
 */
final class ContentTemplate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ContentTemplateFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): ContentTemplateFactory
    {
        return ContentTemplateFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'content_templates';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'structure',
        'default_data',
        'config',
        'preview',
        'thumbnail_url',
        'tags',
        'is_active',
        'is_premium',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'structure' => 'array',
            'default_data' => 'array',
            'config' => 'array',
            'preview' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
            'is_premium' => 'boolean',
            'usage_count' => 'integer',
            'rating' => 'decimal:2',
        ];
    }

    /**
     * Scope: Tylko aktywne szablony.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Tylko darmowe szablony.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeFree(Builder $query): Builder
    {
        return $query->where('is_premium', false);
    }

    /**
     * Scope: Tylko premium szablony.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePremium(Builder $query): Builder
    {
        return $query->where('is_premium', true);
    }

    /**
     * Scope: Filtruj po kategorii.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Zwiększ licznik użyć.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Ustaw ocenę szablonu.
     */
    public function setRating(float $rating): void
    {
        $this->rating = max(0, min(5, $rating)); // Clamp 0-5
        $this->save();
    }
}
