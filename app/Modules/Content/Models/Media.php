<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Model dla Media Manager.
 *
 * Przechowuje metadane plików (obrazy, dokumenty, video) z obsługą
 * automatycznych wariantów, dominant colors, i multi-tenancy.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string|null $project_id UUID projektu (opcjonalne)
 * @property string $file_name Oryginalna nazwa pliku
 * @property string $file_path Ścieżka w storage (relative)
 * @property string $mime_type MIME type (image/jpeg, application/pdf, etc.)
 * @property int $size Rozmiar w bajtach
 * @property string $disk Storage disk (local, s3, etc.)
 * @property int|null $width Szerokość (dla obrazów)
 * @property int|null $height Wysokość (dla obrazów)
 * @property string|null $alt_text Alt text dla obrazów
 * @property string|null $caption Caption/opis
 * @property array<string, mixed>|null $metadata EXIF, IPTC, etc.
 * @property array<string, mixed>|null $variants Różne rozmiary (thumbnail, medium, large)
 * @property array<string>|null $dominant_colors Dominujące kolory (RGB)
 * @property string|null $collection Kategoria/collection
 * @property array<string>|null $tags Tagi
 * @property bool $is_public Czy publiczny
 * @property bool $is_active Czy aktywny
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static static create(array<string, mixed> $attributes = [])
 */
final class Media extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): \Database\Factories\MediaFactory
    {
        return \Database\Factories\MediaFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'media';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'file_name',
        'file_path',
        'mime_type',
        'size',
        'disk',
        'width',
        'height',
        'alt_text',
        'caption',
        'metadata',
        'variants',
        'dominant_colors',
        'collection',
        'tags',
        'is_public',
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
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
            'variants' => 'array',
            'dominant_colors' => 'array',
            'tags' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope: Tylko aktywne media.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Media>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Media>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Tylko publiczne media.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Media>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Media>
     */
    public function scopePublic(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: Filtruj po kolekcji.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Media>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Media>
     */
    public function scopeInCollection(\Illuminate\Database\Eloquent\Builder $query, string $collection): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('collection', $collection);
    }

    /**
     * Scope: Tylko obrazy.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Media>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Media>
     */
    public function scopeImages(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Sprawdź czy to obraz.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Pobierz URL do pliku.
     */
    public function getUrl(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    /**
     * Pobierz URL do wariantu (np. thumbnail, medium, large).
     */
    public function getVariantUrl(string $variant = 'thumbnail'): ?string
    {
        $variants = $this->variants ?? [];

        if (! isset($variants[$variant])) {
            return null;
        }

        $variantPath = $variants[$variant]['path'] ?? null;

        if (! $variantPath) {
            return null;
        }

        return Storage::url($variantPath);
    }

    /**
     * Pobierz pełną ścieżkę do pliku.
     */
    public function getFullPath(): string
    {
        return Storage::disk($this->disk)->path($this->file_path);
    }

    /**
     * Sprawdź czy plik istnieje.
     */
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->file_path);
    }
}
