<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model zlecenia - główny model workflow sprzedaży.
 *
 * @property string $id
 * @property string $customer_id
 * @property string|null $site_id
 * @property string $order_number
 * @property string $type
 * @property string $status
 * @property string $title
 * @property float $price
 */
class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'site_id',
        'listing_id',
        'spec_template_id',
        'parent_order_id',
        'order_number',
        'type',
        'status',
        'title',
        'scope',
        'requirements',
        'price',
        'currency',
        'estimated_days',
        'deadline_at',
        'free_corrections_until',
        'useme_offer_url',
        'offer_sent_at',
        'accepted_at',
        'started_at',
        'delivered_at',
        'paid_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'dispute_reason',
        'dispute_url',
        'dispute_resolution',
        'refund_amount',
        'transferred_to',
        'transferred_reason',
        'transferred_at',
        'internal_notes',
        'assigned_to',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'deadline_at' => 'date',
        'free_corrections_until' => 'datetime',
        'offer_sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'delivered_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'transferred_at' => 'datetime',
    ];

    // Relacje

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function specTemplate(): BelongsTo
    {
        return $this->belongsTo(SpecTemplate::class);
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    public function childOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(Correction::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    // Helpers

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function hasFreeCorrections(): bool
    {
        return $this->free_corrections_until && $this->free_corrections_until->isFuture();
    }

    public function getDaysUntilDeadlineAttribute(): ?int
    {
        return $this->deadline_at?->diffInDays(now(), false);
    }

    /**
     * Generuje następny numer zlecenia.
     */
    public static function generateOrderNumber(): string
    {
        $year = date('Y');
        $lastOrder = static::whereYear('created_at', $year)
            ->orderByDesc('order_number')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('ZLC-%s-%04d', $year, $nextNumber);
    }
}
