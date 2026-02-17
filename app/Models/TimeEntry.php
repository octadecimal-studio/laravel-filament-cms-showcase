<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model wpisu czasu pracy.
 *
 * @property string $id
 * @property int $user_id
 * @property string $description
 * @property int|null $duration_minutes
 * @property bool $is_billable
 * @property string $category
 */
class TimeEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'listing_id',
        'site_id',
        'order_id',
        'correction_id',
        'description',
        'started_at',
        'ended_at',
        'duration_minutes',
        'is_billable',
        'is_billed',
        'hourly_rate',
        'invoice_id',
        'category',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_billable' => 'boolean',
        'is_billed' => 'boolean',
        'hourly_rate' => 'decimal:2',
    ];

    // Relacje

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(Correction::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // Helpers

    public function getDurationHoursAttribute(): float
    {
        return $this->duration_minutes ? round($this->duration_minutes / 60, 2) : 0;
    }

    public function getBillableAmountAttribute(): float
    {
        if (!$this->is_billable || !$this->hourly_rate) {
            return 0;
        }
        return $this->getDurationHoursAttribute() * $this->hourly_rate;
    }

    /**
     * Automatycznie oblicza duration_minutes jeśli jest ended_at.
     */
    public function calculateDuration(): void
    {
        if ($this->started_at && $this->ended_at) {
            $this->duration_minutes = $this->started_at->diffInMinutes($this->ended_at);
        }
    }
}
