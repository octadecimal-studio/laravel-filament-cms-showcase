<?php

declare(strict_types=1);

namespace App\Modules\Deploy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model rekordu DNS dla Deployment Pipeline.
 *
 * @property string $id UUID
 * @property string $domain_id UUID domeny
 * @property string $type Typ rekordu: A, AAAA, MX, TXT, CNAME, NS
 * @property string|null $subdomain Subdomena (puste dla root domain)
 * @property string $target Wartość rekordu (IP, hostname, tekst)
 * @property int|null $priority Priorytet (dla rekordów MX)
 * @property int $ttl Time to live w sekundach
 * @property string|null $ovh_record_id ID rekordu w OVH API
 * @property string $status Status: pending, synced, failed
 * @property \Illuminate\Support\Carbon|null $synced_at Data synchronizacji z OVH
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Domain $domain
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
final class DNSRecord extends Model
{
    /** @use HasFactory<\Database\Factories\DNSRecordFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): \Database\Factories\DNSRecordFactory
    {
        return \Database\Factories\DNSRecordFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'dns_records';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'domain_id',
        'type',
        'subdomain',
        'target',
        'priority',
        'ttl',
        'ovh_record_id',
        'status',
        'synced_at',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'ttl' => 'integer',
            'synced_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Typy rekordów DNS.
     */
    public const TYPE_A = 'A';
    public const TYPE_AAAA = 'AAAA';
    public const TYPE_MX = 'MX';
    public const TYPE_TXT = 'TXT';
    public const TYPE_CNAME = 'CNAME';
    public const TYPE_NS = 'NS';

    /**
     * Statusy synchronizacji.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';

    /**
     * Relacja: DNSRecord należy do Domain.
     *
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Scope: Filtruj po typie rekordu.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filtruj po statusie.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Sprawdza czy rekord jest zsynchronizowany z OVH.
     */
    public function isSynced(): bool
    {
        return $this->status === self::STATUS_SYNCED && $this->ovh_record_id !== null;
    }
}
