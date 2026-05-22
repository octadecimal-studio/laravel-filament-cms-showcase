<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class GoogleCalendarSetting extends Model
{
    protected $table = 'google_calendar_settings';

    protected $guarded = [];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at'     => 'datetime',
    ];

    public static function instance(): self
    {
        return static::firstOrCreate([]);
    }

    public function isConnected(): bool
    {
        return ! empty($this->getRawOriginal('access_token'))
            && ! empty($this->calendar_id);
    }

    public function hasCredentials(): bool
    {
        return ! empty($this->getRawOriginal('client_id'))
            && ! empty($this->getRawOriginal('client_secret'));
    }

    public function setClientIdAttribute(?string $value): void
    {
        $this->attributes['client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getClientIdAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setClientSecretAttribute(?string $value): void
    {
        $this->attributes['client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getClientSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }
}
