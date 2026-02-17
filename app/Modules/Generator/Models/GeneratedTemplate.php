<?php

declare(strict_types=1);

namespace App\Modules\Generator\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Database\Factories\GeneratedTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @use HasFactory<\Database\Factories\GeneratedTemplateFactory>
 */

/**
 * Model wygenerowanych szablonów przez AI.
 *
 * Przechowuje wyniki generowania szablonów przez AI (Claude/OpenAI).
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string|null $prompt_template_id UUID prompt template użytego
 * @property string $prompt Użyty prompt
 * @property string|null $image_url URL obrazka użytego jako input (Vision API)
 * @property string $model Model AI użyty (claude-sonnet-4, gpt-4, etc.)
 * @property string $status Status: pending, generating, completed, failed
 * @property array<string, mixed>|null $generated_code Wygenerowany kod (komponenty, style)
 * @property array<string, mixed>|null $metadata Metadane generowania (tokens, cost, time)
 * @property string|null $error_message Komunikat błędu (jeśli failed)
 * @property float|null $success_score Ocena jakości (0.0-1.0)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class GeneratedTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'generated_templates';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'prompt_template_id',
        'prompt',
        'image_url',
        'model',
        'status',
        'generated_code',
        'metadata',
        'error_message',
        'success_score',
    ];

    /**
     * Rzutowanie atrybutów.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_code' => 'array',
            'metadata' => 'array',
            'success_score' => 'decimal:2',
        ];
    }

    /**
     * Relacja: PromptTemplate użyty do generowania.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<PromptTemplate, $this>
     */
    public function promptTemplate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }

    /**
     * Scope: Tylko zakończone generowania.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCompleted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Tylko nieudane generowania.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeFailed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Oznacz jako zakończone.
     *
     * @param  array<string, mixed>  $generatedCode
     * @param  array<string, mixed>  $metadata
     */
    public function markAsCompleted(array $generatedCode, array $metadata = []): void
    {
        $this->update([
            'status' => 'completed',
            'generated_code' => $generatedCode,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Oznacz jako nieudane.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Utwórz nową instancję factory dla modelu.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return GeneratedTemplateFactory::new();
    }
}
