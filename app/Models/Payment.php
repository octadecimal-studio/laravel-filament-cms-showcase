<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model płatności.
 *
 * @property string $id
 * @property string $customer_id
 * @property float $amount
 * @property string $status
 * @property string|null $payment_method
 */
class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'transaction_id',
        'paid_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // Relacje

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Helpers

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
}
