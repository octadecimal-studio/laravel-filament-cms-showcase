<?php

declare(strict_types=1);

namespace App\Modules\Deploy\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model domeny dla Deployment Pipeline.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string|null $project_id UUID projektu (opcjonalne)
 * @property string $domain Pełna nazwa domeny (np. example.com)
 * @property string|null $subdomain Subdomena (np. dev, www)
 * @property string $dns_status Status DNS: pending, propagating, active, failed
 * @property string $ssl_status Status SSL: pending, requested, active, expired, failed
 * @property \Illuminate\Support\Carbon|null $dns_checked_at Data ostatniego sprawdzenia DNS
 * @property \Illuminate\Support\Carbon|null $ssl_expires_at Data wygaśnięcia certyfikatu SSL
 * @property string|null $vps_ip IP serwera VPS
 * @property string|null $mail_hostname Hostname dla email (np. mail.example.com)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DNSRecord> $dnsRecords
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
final class Domain extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\DomainFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): \Database\Factories\DomainFactory
    {
        return \Database\Factories\DomainFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'domains';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'domain',
        'subdomain',
        'dns_status',
        'ssl_status',
        'dns_checked_at',
        'ssl_expires_at',
        'vps_ip',
        'mail_hostname',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dns_checked_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Statusy DNS.
     */
    public const DNS_STATUS_PENDING = 'pending';
    public const DNS_STATUS_PROPAGATING = 'propagating';
    public const DNS_STATUS_ACTIVE = 'active';
    public const DNS_STATUS_FAILED = 'failed';

    /**
     * Statusy SSL.
     */
    public const SSL_STATUS_PENDING = 'pending';
    public const SSL_STATUS_REQUESTED = 'requested';
    public const SSL_STATUS_ACTIVE = 'active';
    public const SSL_STATUS_EXPIRED = 'expired';
    public const SSL_STATUS_FAILED = 'failed';

    /**
     * Relacja: Domain ma wiele rekordów DNS.
     *
     * @return HasMany<DNSRecord, $this>
     */
    public function dnsRecords(): HasMany
    {
        return $this->hasMany(DNSRecord::class);
    }

    /**
     * Scope: Filtruj po statusie DNS.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithDnsStatus($query, string $status)
    {
        return $query->where('dns_status', $status);
    }

    /**
     * Scope: Filtruj po statusie SSL.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithSslStatus($query, string $status)
    {
        return $query->where('ssl_status', $status);
    }

    /**
     * Sprawdza czy DNS jest aktywny.
     */
    public function isDnsActive(): bool
    {
        return $this->dns_status === self::DNS_STATUS_ACTIVE;
    }

    /**
     * Sprawdza czy SSL jest aktywny.
     */
    public function isSslActive(): bool
    {
        return $this->ssl_status === self::SSL_STATUS_ACTIVE;
    }

    /**
     * Sprawdza czy SSL wygasa wkrótce (< 30 dni).
     */
    public function isSslExpiringSoon(): bool
    {
        if ($this->ssl_expires_at === null) {
            return false;
        }

        return $this->ssl_expires_at->diffInDays(now()) < 30;
    }
}
