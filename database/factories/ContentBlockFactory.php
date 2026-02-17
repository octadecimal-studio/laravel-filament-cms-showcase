<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Content\Models\ContentBlock;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory dla ContentBlock.
 *
 * @extends Factory<ContentBlock>
 */
final class ContentBlockFactory extends Factory
{
    /**
     * Model dla factory.
     */
    protected $model = ContentBlock::class;

    /**
     * Definicja domyślnych atrybutów.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => fake()->unique()->slug(),
            'category' => fake()->randomElement(['hero', 'features', 'cta', 'testimonials', 'gallery']),
            'description' => fake()->sentence(),
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                ],
            ],
            'default_data' => [
                'title' => fake()->sentence(),
                'content' => fake()->paragraph(),
            ],
            'config' => [
                'background' => 'white',
                'spacing' => 'normal',
            ],
            'icon' => fake()->randomElement(['heroicon-o-document', 'heroicon-o-photo', 'heroicon-o-sparkles']),
            'is_active' => true,
            'usage_count' => 0,
        ];
    }

    /**
     * State: Inactive block.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * State: Hero category.
     */
    public function hero(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'hero',
            'icon' => 'heroicon-o-sparkles',
        ]);
    }

    /**
     * State: Features category.
     */
    public function features(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'features',
            'icon' => 'heroicon-o-star',
        ]);
    }

    /**
     * State: Dla konkretnego tenanta.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
