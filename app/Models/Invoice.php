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
 * Model faktury.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $invoice_number
 * @property string $status
 * @property float $total
 */
class Invoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'order_id',
        'invoice_number',
        'status',
        'type',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'currency',
        'issue_date',
        'due_date',
        'paid_at',
        'buyer_name',
        'buyer_nip',
        'buyer_address',
        'pdf_url',
        'stripe_invoice_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    // Relacje

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
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
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'sent' && $this->due_date->isPast();
    }

    public function getRemainingAmountAttribute(): float
    {
        $paid = $this->payments()->where('status', 'completed')->sum('amount');
        return max(0, $this->total - $paid);
    }

    /**
     * Generuje następny numer faktury.
     */
    public static function generateInvoiceNumber(string $type = 'invoice'): string
    {
        $prefix = $type === 'proforma' ? 'PRO' : 'FV';
        $year = date('Y');
        $month = date('m');

        $lastInvoice = static::where('type', $type)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderByDesc('invoice_number')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s/%s/%s/%04d', $prefix, $year, $month, $nextNumber);
    }
}
