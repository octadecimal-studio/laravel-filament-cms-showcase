<?php

declare(strict_types=1);

namespace App\Plugins\Core\Models;

use App\Models\Site;
use App\Models\User;
use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model instalacji pluginu na stronie.
 *
 * Każda strona może mieć zainstalowane różne pluginy.
 * Konfiguracja pluginu jest per-site (w kolumnie config).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $site_id
 * @property string $plugin_slug
 * @property string $version
 * @property array $config
 * @property string $status
 * @property \Carbon\Carbon $installed_at
 * @property string|null $installed_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PluginInstallation extends Model
{
    use HasUuids, BelongsToTenant;

    /**
     * Tabela w bazie danych.
     *
     * @var string
     */
    protected $table = 'plugin_installations';

    /**
     * Pola możliwe do masowego przypisania.
     *
     * @var array<string>
     */
    protected $fillable = [
        'site_id',
        'plugin_slug',
        'version',
        'config',
        'status',
        'installed_at',
        'installed_by',
    ];

    /**
     * Rzutowanie typów.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'config' => 'array',
        'installed_at' => 'datetime',
    ];

    /**
     * Statusy instalacji.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_PENDING_UPGRADE = 'pending_upgrade';

    /**
     * Lista dostępnych statusów.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktywny',
            self::STATUS_DISABLED => 'Wyłączony',
            self::STATUS_PENDING_UPGRADE => 'Oczekuje aktualizacji',
        ];
    }

    /**
     * Relacja do strony.
     *
     * @return BelongsTo
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Relacja do użytkownika, który zainstalował.
     *
     * @return BelongsTo
     */
    public function installedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    /**
     * Scope: tylko aktywne instalacje.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: instalacje konkretnego pluginu.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $slug
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPlugin($query, string $slug)
    {
        return $query->where('plugin_slug', $slug);
    }

    /**
     * Sprawdzenie czy instalacja jest aktywna.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Aktywacja instalacji.
     *
     * @return bool
     */
    public function activate(): bool
    {
        $this->status = self::STATUS_ACTIVE;
        return $this->save();
    }

    /**
     * Dezaktywacja instalacji.
     *
     * @return bool
     */
    public function disable(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    /**
     * Pobranie wartości z konfiguracji.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Ustawienie wartości w konfiguracji.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setConfig(string $key, $value): self
    {
        $config = $this->config ?? [];
        data_set($config, $key, $value);
        $this->config = $config;
        return $this;
    }
}
