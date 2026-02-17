<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Generator\Models\PromptTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Generator\Models\PromptTemplate>
 */
final class PromptTemplateFactory extends Factory
{
    /**
     * Nazwa modelu dla factory.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = PromptTemplate::class;

    /**
     * Definiuj domyślny stan modelu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(['hero', 'features', 'gallery', 'contact', 'full-page']),
            'prompt_text' => 'Wygeneruj komponent {{category}} dla {{prompt}}',
            'variables' => ['category', 'prompt'],
            'description' => fake()->sentence(),
            'usage_count' => 0,
            'success_rate' => fake()->randomFloat(2, 0.5, 1.0),
            'avg_score' => fake()->randomFloat(2, 0.7, 1.0),
            'is_active' => true,
        ];
    }
}
