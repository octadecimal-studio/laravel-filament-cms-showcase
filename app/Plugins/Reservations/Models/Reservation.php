<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Models;

use App\Models\Site;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model rezerwacji.
 *
 * Przechowuje dane z formularza rezerwacji z frontendu.
 * Powiązany z konkretnym motocyklem i stroną (Site).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $site_id
 * @property string|null $motorcycle_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $customer_phone
 * @property \Carbon\Carbon $pickup_date
 * @property \Carbon\Carbon $return_date
 * @property string $status
 * @property float|null $total_price
 * @property string|null $notes
 * @property bool $rodo_consent
 * @property \Carbon\Carbon|null $rodo_consent_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Reservation extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant;

    /**
     * Nazwa tabeli.
     *
     * @var string
     */
    protected $table = 'plugin_reservations_reservations';

    /**
     * Pola możliwe do masowego przypisania.
     *
     * @var array<string>
     */
    protected $fillable = [
        'site_id',
        'motorcycle_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'pickup_date',
        'return_date',
        'status',
        'total_price',
        'notes',
        'rodo_consent',
        'rodo_consent_at',
    ];

    /**
     * Rzutowanie typów.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pickup_date' => 'date',
        'return_date' => 'date',
        'total_price' => 'decimal:2',
        'rodo_consent' => 'boolean',
        'rodo_consent_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Statusy
    // -------------------------------------------------------------------------

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    /**
     * Lista dostępnych statusów.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Oczekująca',
            self::STATUS_CONFIRMED => 'Potwierdzona',
            self::STATUS_CANCELLED => 'Anulowana',
            self::STATUS_COMPLETED => 'Zakończona',
        ];
    }

    /**
     * Kolory dla statusów (Filament).
     *
     * @return array<string, string>
     */
    public static function statusColors(): array
    {
        return [
            self::STATUS_PENDING => 'warning',
            self::STATUS_CONFIRMED => 'success',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_COMPLETED => 'info',
        ];
    }

    // -------------------------------------------------------------------------
    // Relacje
    // -------------------------------------------------------------------------

    /**
     * Strona, z której pochodzi rezerwacja.
     *
     * @return BelongsTo
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Zarezerwowany motocykl (two_wheels_motorcycles).
     * motorcycle_id to UUID – bez FK w DB, odczyt przez relację.
     *
     * @return BelongsTo<Motorcycle, $this>
     */
    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class, 'motorcycle_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: rezerwacje oczekujące.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: rezerwacje potwierdzone.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope: nadchodzące rezerwacje (pickup_date >= today).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpcoming($query)
    {
        return $query->where('pickup_date', '>=', now()->startOfDay());
    }

    /**
     * Scope: rezerwacje dla konkretnej strony.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $siteId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSite($query, string $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Oblicza liczbę dni rezerwacji.
     *
     * @return int|null
     */
    public function getDaysAttribute(): ?int
    {
        if (!$this->pickup_date || !$this->return_date) {
            return null;
        }
        return (int) $this->pickup_date->diffInDays($this->return_date);
    }

    /**
     * Czy rezerwacja jest oczekująca.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Czy rezerwacja jest potwierdzona.
     *
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Potwierdź rezerwację.
     *
     * @return bool
     */
    public function confirm(): bool
    {
        $this->status = self::STATUS_CONFIRMED;
        return $this->save();
    }

    /**
     * Anuluj rezerwację.
     *
     * @return bool
     */
    public function cancel(): bool
    {
        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Oznacz rezerwację jako zakończoną.
     *
     * @return bool
     */
    public function complete(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        return $this->save();
    }

    /**
     * Opis statusu.
     *
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        if (!$this->status) {
            return 'Brak statusu';
        }
        return self::statuses()[$this->status] ?? $this->status;
    }
}
