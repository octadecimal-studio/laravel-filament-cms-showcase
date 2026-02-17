<?php

declare(strict_types=1);

namespace App\Modules\Generator\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model gotowych szablonów Next.js.
 *
 * Reprezentuje gotowe szablony z katalogu templates/ dostępne do użycia w projektach.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $name Nazwa szablonu
 * @property string $slug Slug (unikalny)
 * @property string $directory_path Ścieżka do katalogu szablonu (względem templates/)
 * @property string|null $category Kategoria (portfolio, landing, corporate, blog)
 * @property array<string> $tech_stack Stack technologiczny (Next.js, TypeScript, Tailwind)
 * @property string|null $description Opis szablonu
 * @property array<string, mixed>|null $metadata Metadane (komponenty, style, zależności)
 * @property string|null $thumbnail_url URL miniaturki
 * @property string $analysis_status Status analizy AI (pending, analyzing, completed, failed)
 * @property int $analysis_progress Progress analizy AI w % (0-100)
 * @property string|null $preview_url URL preview (iframe)
 * @property string|null $webhook_url URL webhooka do rewalidacji cache (Next.js)
 * @property string|null $deployment_env Środowisko wdrożenia (dev, prd, tst)
 * @property array<string>|null $tags Tagi
 * @property bool $is_active Czy aktywny
 * @property bool $is_premium Czy premium
 * @property int $usage_count Licznik użyć
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class Template extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\TemplateFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): \Database\Factories\TemplateFactory
    {
        return \Database\Factories\TemplateFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'templates';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'directory_path',
        'category',
        'tech_stack',
        'description',
        'metadata',
        'thumbnail_url',
        'preview_url',
        'webhook_url',
        'deployment_env',
        'tags',
        'is_active',
        'is_premium',
        'analysis_status',
        'analysis_progress',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tech_stack' => 'array',
            'metadata' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
            'is_premium' => 'boolean',
            'usage_count' => 'integer',
            'analysis_progress' => 'integer',
        ];
    }

    /**
     * Scope: Tylko aktywne szablony.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filtruj po kategorii.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfCategory(\Illuminate\Database\Eloquent\Builder $query, string $category): \Illuminate\Database\Eloquent\Builder
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
     * Pobierz pełną ścieżkę do katalogu szablonu.
     */
    public function getFullPath(): string
    {
        return base_path('templates/'.$this->directory_path);
    }

    /**
     * Pobierz URL do screenshot szablonu.
     */
    public function getScreenshotUrl(): ?string
    {
        // Jeśli mamy thumbnail_url, użyj go
        if ($this->thumbnail_url) {
            // Jeśli to ścieżka względna, zwróć pełny URL
            if (! str_starts_with($this->thumbnail_url, 'http')) {
                return asset($this->thumbnail_url);
            }

            return $this->thumbnail_url;
        }

        // Sprawdź czy istnieje screenshot.png w katalogu szablonu
        $screenshotPath = $this->getFullPath().'/screenshot.png';
        if (file_exists($screenshotPath)) {
            $relativePath = "templates/{$this->directory_path}/screenshot.png";
            $url = asset($relativePath);

            return is_string($url) ? $url : null;
        }

        return null;
    }

    /**
     * Pobierz URL do preview (zbudowana wersja w out/).
     */
    public function getPreviewUrl(): ?string
    {
        // Jeśli mamy preview_url, użyj go
        if ($this->preview_url) {
            return $this->preview_url;
        }

        // Sprawdź czy istnieje zbudowana wersja w out/
        $outPath = $this->getFullPath().'/out/index.html';
        if (file_exists($outPath)) {
            $relativePath = "templates/{$this->directory_path}/out/index.html";
            $url = asset($relativePath);

            return is_string($url) ? $url : null;
        }

        return null;
    }
}
