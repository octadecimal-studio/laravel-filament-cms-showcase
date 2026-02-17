<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model kroku procesu wypożyczenia.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property int $step_number Numer kroku (1-10, unique)
 * @property string $title Tytuł
 * @property string $description Opis
 * @property string $icon_name Nazwa ikony
 * @property bool $published Czy opublikowane
 * @property \Illuminate\Support\Carbon|null $published_at Data publikacji
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class ProcessStep extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\ProcessStepFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'two_wheels_process_steps';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'step_number',
        'title',
        'description',
        'icon_name',
        'published',
        'published_at',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
