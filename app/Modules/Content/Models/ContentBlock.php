<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use Database\Factories\ContentBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model reużywalnych bloków treści.
 *
 * ContentBlock definiuje strukturę (schema) wielokrotnie używanych elementów.
 * Przykłady: Hero Section, Features Grid, CTA Box, Testimonials, etc.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $name Nazwa bloku
 * @property string $slug Unikalny identyfikator
 * @property string|null $category Kategoria (hero, features, cta)
 * @property string|null $description Opis
 * @property array<string, mixed> $schema JSON Schema definicja pól
 * @property array<string, mixed>|null $default_data Domyślne wartości
 * @property array<string, mixed>|null $config Dodatkowa konfiguracja
 * @property string|null $icon Ikona dla UI
 * @property array<string, mixed>|null $preview Dane preview
 * @property bool $is_active Czy aktywny
 * @property int $usage_count Licznik użyć
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Builder<static> query()
 */
final class ContentBlock extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ContentBlockFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): ContentBlockFactory
    {
        return ContentBlockFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'content_blocks';

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
        'schema',
        'default_data',
        'config',
        'icon',
        'preview',
        'is_active',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'default_data' => 'array',
            'config' => 'array',
            'preview' => 'array',
            'is_active' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    /**
     * Scope: Tylko aktywne bloki.
     *
     * @param Builder<ContentBlock> $query
     * @return Builder<ContentBlock>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filtruj po kategorii.
     *
     * @param Builder<ContentBlock> $query
     * @return Builder<ContentBlock>
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
     * Zmniejsz licznik użyć.
     */
    public function decrementUsage(): void
    {
        $this->decrement('usage_count');
    }
}
