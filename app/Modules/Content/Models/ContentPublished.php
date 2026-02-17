<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model publikacji kontentu na środowisku.
 *
 * Śledzi która wersja kontentu jest opublikowana na staging/production.
 * Jeden kontent może mieć różne wersje na różnych środowiskach.
 *
 * @property string $id UUID
 * @property string $content_id UUID kontentu
 * @property string $environment Środowisko: staging, production
 * @property string $version_id UUID opublikowanej wersji
 * @property \Illuminate\Support\Carbon $published_at Data publikacji
 * @property string|null $published_by UUID użytkownika
 * @property array<string, mixed>|null $publish_notes Notatki do publikacji
 * @property bool $auto_published Czy auto-publish
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SiteContent $content
 * @property-read ContentVersion $version
 * @property-read User|null $publisher
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
final class ContentPublished extends Model
{
    /** @use HasFactory<\Database\Factories\ContentPublishedFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'content_published';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'content_id',
        'environment',
        'version_id',
        'published_at',
        'published_by',
        'publish_notes',
        'auto_published',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'publish_notes' => 'array',
            'auto_published' => 'boolean',
        ];
    }

    /**
     * Relacja: Kontent którego dotyczy publikacja.
     *
     * @return BelongsTo<SiteContent, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SiteContent::class, 'content_id');
    }

    /**
     * Relacja: Opublikowana wersja.
     *
     * @return BelongsTo<ContentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'version_id');
    }

    /**
     * Relacja: Użytkownik który opublikował.
     *
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Scope: Dla staging.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeStaging(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('environment', 'staging');
    }

    /**
     * Scope: Dla production.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeProduction(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('environment', 'production');
    }

    /**
     * Scope: Dla konkretnego środowiska.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEnvironment(\Illuminate\Database\Eloquent\Builder $query, string $environment): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('environment', $environment);
    }

    /**
     * Sprawdź czy to publikacja na staging.
     */
    public function isStaging(): bool
    {
        return $this->environment === 'staging';
    }

    /**
     * Sprawdź czy to publikacja na production.
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}
