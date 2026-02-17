<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model domeny przypisanej do strony.
 *
 * @property string $id
 * @property string $site_id
 * @property string $domain
 * @property bool $is_primary
 * @property string $dns_status
 * @property string $ssl_status
 */
class SiteDomain extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'site_id',
        'domain',
        'is_primary',
        'dns_status',
        'ssl_status',
        'dns_records',
        'ssl_expires_at',
        'verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'dns_records' => 'array',
        'ssl_expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // Relacje

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->dns_status === 'active' && $this->ssl_status === 'active';
    }

    public function isSslExpiringSoon(): bool
    {
        return $this->ssl_expires_at && $this->ssl_expires_at->diffInDays(now()) < 30;
    }

    public function getFullUrlAttribute(): string
    {
        return 'https://' . $this->domain;
    }
}
