<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal Site model for MotoRent Demo.
 *
 * Used by Reservations plugin (reservation.site_id FK) and V1 Content API.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $production_url
 * @property string|null $staging_url
 */
class Site extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'status',
        'production_url',
        'staging_url',
    ];

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function getUrlAttribute(): ?string
    {
        return $this->production_url ?? $this->staging_url;
    }
}
