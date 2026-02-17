<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model feature (zalety firmy).
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $title Tytuł
 * @property string $description Opis
 * @property string|null $icon_id UUID ikony (Media)
 * @property int $order Kolejność
 * @property bool $published Czy opublikowane
 * @property \Illuminate\Support\Carbon|null $published_at Data publikacji
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Modules\Content\Models\Media|null $icon
 */
final class Feature extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\FeatureFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'two_wheels_features';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'icon_id',
        'order',
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
            'order' => 'integer',
            'published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Relacja: Ikona (Media).
     *
     * @return BelongsTo<\App\Modules\Content\Models\Media, $this>
     */
    public function icon(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Content\Models\Media::class, 'icon_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }
}
