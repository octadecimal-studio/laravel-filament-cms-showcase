<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model środowiska strony (staging, production).
 *
 * @property string $id
 * @property string $site_id
 * @property string $type
 * @property string|null $url
 * @property string $deploy_status
 */
class SiteEnvironment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'site_id',
        'type',
        'url',
        'deploy_status',
        'deployed_at',
        'deploy_logs',
        'env_variables',
    ];

    protected $casts = [
        'env_variables' => 'encrypted:array',
        'deployed_at' => 'datetime',
    ];

    // Relacje

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    // Helpers

    public function isDeployed(): bool
    {
        return $this->deploy_status === 'deployed';
    }

    public function isProduction(): bool
    {
        return $this->type === 'production';
    }

    public function isStaging(): bool
    {
        return $this->type === 'staging';
    }
}
