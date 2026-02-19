<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model uwagi cennika.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $content Treść uwagi
 * @property int $sort_order Kolejność
 * @property bool $is_active Czy aktywna
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class PricingNote extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'two_wheels_pricing_notes';

    /** @var list<string> */
    protected $fillable = [
        'content',
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
