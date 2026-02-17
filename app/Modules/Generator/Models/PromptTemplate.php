<?php

declare(strict_types=1);

namespace App\Modules\Generator\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Database\Factories\PromptTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @use HasFactory<\Database\Factories\PromptTemplateFactory>
 */

/**
 * Model biblioteki promptów dla AI Generator.
 *
 * Przechowuje sprawdzone prompty z metadanymi sukcesu.
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $name Nazwa promptu
 * @property string $slug Slug (unikalny)
 * @property string $category Kategoria (hero, features, gallery, contact, full-page)
 * @property string $prompt_text Tekst promptu
 * @property array<string, mixed>|null $variables Zmienne w prompcie ({{variable}})
 * @property string|null $description Opis
 * @property int $usage_count Licznik użyć
 * @property float|null $success_rate Wskaźnik sukcesu (0.0-1.0)
 * @property float|null $avg_score Średnia ocena jakości
 * @property array<string, mixed>|null $examples Przykłady użycia
 * @property bool $is_active Czy aktywny
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class PromptTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'prompt_templates';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'prompt_text',
        'variables',
        'description',
        'usage_count',
        'success_rate',
        'avg_score',
        'examples',
        'is_active',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'examples' => 'array',
            'success_rate' => 'decimal:2',
            'avg_score' => 'decimal:2',
            'usage_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relacja: Wygenerowane szablony używające tego promptu.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<GeneratedTemplate, $this>
     */
    public function generatedTemplates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GeneratedTemplate::class, 'prompt_template_id');
    }

    /**
     * Scope: Tylko aktywne prompty.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filtruj po kategorii.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfCategory(\Illuminate\Database\Eloquent\Builder $query, string $category): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Zwiększ licznik użyć.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Zaktualizuj wskaźnik sukcesu na podstawie wyników.
     *
     * @param  float  $score  Ocena jakości (0.0-1.0)
     */
    public function updateSuccessRate(float $score): void
    {
        $this->incrementUsage();

        // Oblicz nową średnią
        $currentAvg = $this->avg_score ?? 0.0;
        $count = $this->usage_count;
        $newAvg = (($currentAvg * ($count - 1)) + $score) / $count;

        $this->update([
            'avg_score' => $newAvg,
            'success_rate' => $score >= 0.7 ? ($this->success_rate ?? 0.0) + 0.01 : max(0, ($this->success_rate ?? 0.0) - 0.01),
        ]);
    }

    /**
     * Renderuj prompt z zmiennymi.
     *
     * @param  array<string, string>  $variables
     */
    public function render(array $variables = []): string
    {
        $prompt = $this->prompt_text;

        foreach ($variables as $key => $value) {
            $prompt = str_replace("{{{$key}}}", $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Utwórz nową instancję factory dla modelu.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return PromptTemplateFactory::new();
    }
}
