<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model pozycji na fakturze.
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $description
 * @property float $quantity
 * @property float $unit_price
 * @property float $total
 */
class InvoiceItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_rate',
        'total',
        'order_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relacje

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Helpers

    public function getNetTotalAttribute(): float
    {
        return $this->total / (1 + $this->tax_rate / 100);
    }

    public function getTaxAmountAttribute(): float
    {
        return $this->total - $this->getNetTotalAttribute();
    }
}
