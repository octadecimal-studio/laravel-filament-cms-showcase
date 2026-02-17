<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model poprawki zgłoszonej przez klienta.
 *
 * @property string $id
 * @property string $order_id
 * @property string $site_id
 * @property string $title
 * @property string $status
 * @property bool $is_free
 */
class Correction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'order_id',
        'site_id',
        'reported_by',
        'title',
        'description',
        'page_url',
        'status',
        'is_free',
        'estimated_price',
        'rejection_reason',
        'assigned_to',
        'reported_at',
        'accepted_at',
        'completed_at',
        'verified_at',
        'deployed_at',
        'verified_by',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'estimated_price' => 'decimal:2',
        'reported_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'deployed_at' => 'datetime',
    ];

    // Relacje

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    // Helpers

    public function isDone(): bool
    {
        return in_array($this->status, ['done', 'verified', 'deployed']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['reported', 'accepted', 'in_progress']);
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Sprawdza czy poprawka jest darmowa na podstawie daty zamówienia.
     */
    public function calculateIsFree(): bool
    {
        if (!$this->order || !$this->order->free_corrections_until) {
            return false;
        }

        return now()->lt($this->order->free_corrections_until);
    }
}
