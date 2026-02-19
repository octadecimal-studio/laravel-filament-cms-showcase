<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model warunku wypożyczenia.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $title Tytuł
 * @property string $description Opis (HTML)
 * @property string|null $icon Nazwa ikony (heroicon)
 * @property int $sort_order Kolejność
 * @property bool $is_active Czy aktywny
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class RentalCondition extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'two_wheels_rental_conditions';

    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
