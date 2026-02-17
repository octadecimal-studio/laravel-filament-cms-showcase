<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model klienta (zleceniodawcy).
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $company_name
 * @property string|null $nip
 * @property string|null $email
 * @property string|null $phone
 * @property string $status
 * @property string|null $source
 */
class Customer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'company_name',
        'nip',
        'regon',
        'email',
        'phone',
        'website',
        'address_street',
        'address_city',
        'address_postal',
        'address_country',
        'billing_address_street',
        'billing_address_city',
        'billing_address_postal',
        'billing_address_country',
        'source',
        'source_url',
        'referral_code',
        'notes',
        'internal_notes',
        'status',
        'is_vip',
        'max_sites',
        'settings',
        'first_order_at',
        'last_order_at',
        'churned_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_vip' => 'boolean',
        'first_order_at' => 'datetime',
        'last_order_at' => 'datetime',
        'churned_at' => 'datetime',
    ];

    // Relacje

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_user')
            ->withPivot(['role', 'can_view_billing', 'can_manage_users', 'notify_new_invoice', 'notify_site_updates', 'invited_at', 'accepted_at'])
            ->withTimestamps();
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_street,
            $this->address_postal . ' ' . $this->address_city,
            $this->address_country,
        ])->filter()->implode(', ');
    }
}
