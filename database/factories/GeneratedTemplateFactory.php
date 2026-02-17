<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Generator\Models\GeneratedTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Generator\Models\GeneratedTemplate>
 */
final class GeneratedTemplateFactory extends Factory
{
    /**
     * Nazwa modelu dla factory.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = GeneratedTemplate::class;

    /**
     * Definiuj domyślny stan modelu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prompt' => fake()->sentence(),
            'model' => 'claude-sonnet-4-20250514',
            'status' => 'completed',
            'generated_code' => [
                'components' => [
                    [
                        'name' => 'Hero',
                        'type' => 'tsx',
                        'code' => 'export default function Hero() { return <div>Hero</div>; }',
                    ],
                ],
                'metadata' => [
                    'description' => fake()->sentence(),
                    'category' => 'hero',
                ],
            ],
            'metadata' => [
                'tokens_input' => 100,
                'tokens_output' => 200,
                'tokens_total' => 300,
            ],
            'success_score' => fake()->randomFloat(2, 0.7, 1.0),
        ];
    }
}
