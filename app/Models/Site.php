<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Generator\Models\Template;

/**
 * Model strony internetowej klienta.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $staging_url
 * @property string|null $production_url
 */
class Site extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'template_id',
        'name',
        'slug',
        'code',
        'template_slug',
        'status',
        'staging_url',
        'production_url',
        'settings',
        'seo_settings',
        'published_at',
        'suspended_at',
        'pages_count',
        'media_count',
        'last_content_update_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'seo_settings' => 'array',
        'published_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_content_update_at' => 'datetime',
    ];

    // Relacje

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_user')
            ->withPivot(['role', 'can_publish', 'can_manage_media', 'can_view_analytics', 'invited_by', 'invited_at', 'accepted_at', 'last_access_at'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(Correction::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(SiteDomain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(SiteDomain::class)->where('is_primary', true);
    }

    public function environments(): HasMany
    {
        return $this->hasMany(SiteEnvironment::class);
    }

    public function stagingEnvironment(): HasOne
    {
        return $this->hasOne(SiteEnvironment::class)->where('type', 'staging');
    }

    public function productionEnvironment(): HasOne
    {
        return $this->hasOne(SiteEnvironment::class)->where('type', 'production');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    // Helpers

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isDevelopment(): bool
    {
        return $this->status === 'development';
    }

    public function getUrlAttribute(): ?string
    {
        return $this->production_url ?? $this->staging_url;
    }
}
