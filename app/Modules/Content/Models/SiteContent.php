<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use Database\Factories\SiteContentFactory;
use App\Modules\Core\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Site;
use App\Modules\Content\Models\ContentBlock;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model treści CMS.
 *
 * Obsługuje różne typy treści: pages, sections, components, blocks.
 * Umożliwia hierarchię (parent-child), wersjonowanie i flexible content (JSON).
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string|null $site_id UUID strony (Site)
 * @property string|null $project_id UUID projektu (opcjonalne)
 * @property string $type Typ: page, section, component, block
 * @property string|null $content_block_id UUID ContentBlock (opcjonalnie)
 * @property string $title Tytuł
 * @property string|null $slug Slug (dla page)
 * @property string|null $description Opis
 * @property array<string, mixed> $data Dane flexible content (JSON)
 * @property array<string, mixed>|null $meta Meta dane (SEO, OG tags)
 * @property string $status Status: draft, published, archived
 * @property Carbon|null $published_at Data publikacji
 * @property int $order Kolejność sortowania
 * @property string|null $parent_id UUID rodzica (hierarchia)
 * @property bool $is_current_version Czy aktualna wersja
 * @property int $version Numer wersji
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Site|null $site
 * @property-read ContentBlock|null $contentBlock
 * @property-read SiteContent|null $parent
 * @property-read Collection<int, SiteContent> $children
 * @property-read Collection<int, ContentPublished> $publications
 *
 * @method static Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Builder<static> query()
 */
final class SiteContent extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SiteContentFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): SiteContentFactory
    {
        return SiteContentFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'site_contents';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'project_id',
        'type',
        'content_block_id',
        'title',
        'slug',
        'description',
        'data',
        'meta',
        'status',
        'published_at',
        'order',
        'parent_id',
        'is_current_version',
        'version',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'meta' => 'array',
            'published_at' => 'datetime',
            'is_current_version' => 'boolean',
            'order' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * Relacja: Strona (Site) do której należy kontent.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Relacja: Publikacje kontentu na różnych środowiskach.
     *
     * @return HasMany<ContentPublished, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(ContentPublished::class, 'content_id');
    }

    /**
     * Relacja: Rodzic w hierarchii.
     *
     * Wyłączamy TenantScope bo parent i child mają tego samego tenanta.
     *
     * @return BelongsTo<SiteContent, $this>
     */
    public function parent(): BelongsTo
    {
        // PHPStan: withoutGlobalScope returns Builder, not BelongsTo
        /** @phpstan-ignore-next-line return.type */
        return $this->belongsTo(SiteContent::class, 'parent_id')
            ->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Relacja: Dzieci w hierarchii.
     *
     * Wyłączamy TenantScope bo parent i child mają tego samego tenanta.
     *
     * @return HasMany<SiteContent, $this>
     */
    public function children(): HasMany
    {
        // PHPStan: withoutGlobalScope returns Builder, not HasMany
        /** @phpstan-ignore-next-line return.type */
        return $this->hasMany(SiteContent::class, 'parent_id')
            ->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Relacja: ContentBlock (opcjonalnie).
     *
     * @return BelongsTo<ContentBlock, $this>
     */
    public function contentBlock(): BelongsTo
    {
        return $this->belongsTo(ContentBlock::class, 'content_block_id');
    }

    /**
     * Scope: Tylko opublikowane treści.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        // PHPStan: where() with now() returns Query\Builder, explicit cast needed
        /** @phpstan-ignore-next-line return.type */
        return $query->where('status', 'published')
            ->where('is_current_version', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope: Tylko bieżące wersje.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeCurrentVersion(Builder $query): Builder
    {
        return $query->where('is_current_version', true);
    }

    /**
     * Scope: Filtruj po typie.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Sprawdź czy treść jest opublikowana.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    /**
     * Sprawdź czy treść jest wersją roboczą.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Sprawdź czy treść jest zarchiwizowana.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}
