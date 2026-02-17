<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model ogłoszenia z portali (useme, oferteo, itp.).
 *
 * @property string $id
 * @property string $platform
 * @property string|null $external_id
 * @property string $url
 * @property string $title
 * @property string $status
 * @property string $priority
 */
class Listing extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'platform',
        'external_id',
        'url',
        'title',
        'description',
        'budget_min',
        'budget_max',
        'currency',
        'deadline',
        'client_name',
        'client_location',
        'status',
        'priority',
        'notes',
        'found_at',
        'expires_at',
    ];

    protected $casts = [
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'deadline' => 'date',
        'found_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relacje

    public function specTemplates(): HasMany
    {
        return $this->hasMany(SpecTemplate::class);
    }

    public function latestSpecTemplate(): HasOne
    {
        return $this->hasOne(SpecTemplate::class)->latestOfMany();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    // Helpers

    public function isWon(): bool
    {
        return $this->status === 'won';
    }

    public function getBudgetRangeAttribute(): string
    {
        if ($this->budget_min && $this->budget_max) {
            return number_format((float) $this->budget_min, 0) . ' - ' . number_format((float) $this->budget_max, 0) . ' ' . $this->currency;
        }
        if ($this->budget_max) {
            return 'do ' . number_format((float) $this->budget_max, 0) . ' ' . $this->currency;
        }
        return 'brak budżetu';
    }
}
