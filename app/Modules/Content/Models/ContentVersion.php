<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\User;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Model wersji treści.
 *
 * Przechowuje snapshoty modeli w czasie (wersjonowanie).
 * Działa jako polymorphic relation.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $versionable_id UUID wersjonowanego modelu
 * @property string $versionable_type Typ modelu (morphTo)
 * @property int $version Numer wersji
 * @property bool $is_current Czy bieżąca wersja
 * @property array<string, mixed> $data Snapshot danych
 * @property array<string, mixed>|null $changes Diff z poprzednią wersją
 * @property string|null $change_summary Opis zmian
 * @property string|null $created_by UUID użytkownika
 * @property string|null $ip_address IP address
 * @property string|null $user_agent User agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $versionable
 * @property-read User|null $author
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
final class ContentVersion extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\ContentVersionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): \Database\Factories\ContentVersionFactory
    {
        return \Database\Factories\ContentVersionFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'content_versions';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'versionable_id',
        'versionable_type',
        'version',
        'is_current',
        'data',
        'changes',
        'change_summary',
        'created_by',
        'ip_address',
        'user_agent',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_current' => 'boolean',
            'data' => 'array',
            'changes' => 'array',
        ];
    }

    /**
     * Relacja: Wersjonowany model (polymorphic).
     *
     * @return MorphTo<Model, $this>
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relacja: Autor wersji.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Tylko bieżące wersje.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCurrent(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope: Dla konkretnego modelu.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForModel(\Illuminate\Database\Eloquent\Builder $query, Model $model): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('versionable_id', $model->getKey())
            ->where('versionable_type', $model->getMorphClass());
    }

    /**
     * Sprawdź czy to najnowsza wersja.
     */
    public function isLatest(): bool
    {
        return $this->is_current;
    }

    /**
     * Oznacz jako bieżącą wersję (automatycznie odznacza inne).
     */
    public function markAsCurrent(): void
    {
        // Odznacz wszystkie inne wersje tego modelu
        // PHPStan: scopeForModel() is a query scope, not static method
        /** @phpstan-ignore-next-line staticMethod.notFound */
        self::forModel($this->versionable)
            ->where('id', '!=', $this->id)
            ->update(['is_current' => false]);

        // Oznacz tę wersję jako bieżącą
        $this->is_current = true;
        $this->save();
    }
}
