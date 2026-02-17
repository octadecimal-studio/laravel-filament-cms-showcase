<?php

declare(strict_types=1);

namespace App\Modules\Deploy\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model deploymentu dla Deployment Pipeline.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string|null $project_id UUID projektu (opcjonalne)
 * @property string|null $domain_id UUID domeny (opcjonalne)
 * @property string $status Status: pending, in_progress, completed, failed, rolled_back
 * @property string|null $version Wersja deploymentu (np. 20260122-212654)
 * @property array<int, string>|null $logs Logi deploymentu (JSON array)
 * @property array<string, mixed>|null $metadata Dodatkowe metadane (config, environment, etc.)
 * @property \Illuminate\Support\Carbon|null $started_at Data rozpoczęcia
 * @property \Illuminate\Support\Carbon|null $completed_at Data zakończenia
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Domain|null $domain
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
final class Deployment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\DeploymentFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa factory dla modelu.
     */
    protected static function newFactory(): \Database\Factories\DeploymentFactory
    {
        return \Database\Factories\DeploymentFactory::new();
    }

    /**
     * Nazwa tabeli.
     */
    protected $table = 'deployments';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'domain_id',
        'status',
        'version',
        'logs',
        'metadata',
        'started_at',
        'completed_at',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logs' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Statusy deploymentu.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    /**
     * Relacja: Deployment należy do Domain.
     *
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
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
     * Scope: Filtruj aktywne deploymenty (in_progress).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope: Filtruj zakończone deploymenty.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Filtruj nieudane deploymenty.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Dodaje log do deploymentu.
     *
     * @param  string  $message
     * @param  string  $level
     */
    public function addLog(string $message, string $level = 'info'): void
    {
        $logs = $this->logs ?? [];
        $logs[] = [
            'timestamp' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
        ];
        $this->logs = $logs;
        $this->save();
    }

    /**
     * Sprawdza czy deployment jest w toku.
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Sprawdza czy deployment zakończył się sukcesem.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Sprawdza czy deployment zakończył się niepowodzeniem.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Sprawdza czy deployment został wycofany.
     */
    public function isRolledBack(): bool
    {
        return $this->status === self::STATUS_ROLLED_BACK;
    }
}
