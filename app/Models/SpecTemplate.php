<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Generator\Models\Template;

/**
 * Model spekulatywnego szablonu - przygotowanego przed kontaktem z klientem.
 *
 * @property string $id
 * @property string $listing_id
 * @property string|null $template_id
 * @property float $proposed_price
 * @property int $proposed_days
 * @property string $status
 */
class SpecTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'listing_id',
        'template_id',
        'proposed_price',
        'proposed_days',
        'preview_url',
        'screenshot_url',
        'customizations',
        'status',
        'notes',
    ];

    protected $casts = [
        'proposed_price' => 'decimal:2',
        'proposed_days' => 'integer',
        'customizations' => 'array',
    ];

    // Relacje

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    // Helpers

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
