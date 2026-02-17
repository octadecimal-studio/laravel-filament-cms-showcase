<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Content\Models\ContentTemplate;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory dla ContentTemplate.
 *
 * @extends Factory<ContentTemplate>
 */
final class ContentTemplateFactory extends Factory
{
    protected $model = ContentTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'category' => fake()->randomElement(['page', 'section', 'email']),
            'description' => fake()->sentence(),
            'structure' => [
                'layout' => 'default',
                'blocks' => [],
            ],
            'default_data' => [
                'title' => fake()->sentence(),
            ],
            'config' => [
                'colors' => ['primary' => '#000000'],
            ],
            'tags' => [fake()->word(), fake()->word()],
            'is_active' => true,
            'is_premium' => false,
            'usage_count' => 0,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => true,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
