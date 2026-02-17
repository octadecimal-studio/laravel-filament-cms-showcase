<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model dla custom navigation items użytkownika.
 */
class UserCustomNavigationItem extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'user_custom_navigation_items';

    protected $fillable = [
        'user_id',
        'label',
        'icon',
        'url',
        'group',
        'sort_order',
        'is_pinned_to_topbar',
        'is_active',
        'open_in_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_pinned_to_topbar' => 'boolean',
            'is_active' => 'boolean',
            'open_in_new_tab' => 'boolean',
        ];
    }

    /**
     * Relacja: należy do użytkownika.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
