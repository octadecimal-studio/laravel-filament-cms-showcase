<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model testimonial (opinie klientów).
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $author_name Imię autora
 * @property string $content Treść opinii
 * @property int $rating Ocena (1-5)
 * @property string|null $motorcycle_id UUID motocykla (opcjonalne)
 * @property bool $published Czy opublikowane
 * @property int $order Kolejność
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Motorcycle|null $motorcycle
 */
final class Testimonial extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\TestimonialFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'two_wheels_testimonials';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'author_name',
        'content',
        'rating',
        'motorcycle_id',
        'published',
        'order',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'published' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Relacja: Motocykl (opcjonalne).
     *
     * @return BelongsTo<Motorcycle, $this>
     */
    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class, 'motorcycle_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }
}
