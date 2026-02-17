<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Model logu aktywności (audit trail).
 *
 * @property string $id
 * @property int|null $user_id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $action
 * @property array|null $properties
 */
class ActivityLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'subject_type',
        'subject_id',
        'action',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    // Relacje

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }

    // Static helpers

    /**
     * Loguje akcję.
     */
    public static function log(
        string $action,
        Model $subject,
        ?array $properties = null,
        ?User $user = null
    ): static {
        $user = $user ?? auth()->user();

        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
