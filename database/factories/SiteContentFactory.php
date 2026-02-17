<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Content\Models\SiteContent;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory dla SiteContent.
 *
 * @extends Factory<SiteContent>
 */
final class SiteContentFactory extends Factory
{
    /**
     * Model dla factory.
     */
    protected $model = SiteContent::class;

    /**
     * Definicja domyślnych atrybutów.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'page',
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->paragraph(),
            'data' => [
                'sections' => [],
                'settings' => [
                    'layout' => 'default',
                ],
            ],
            'meta' => [
                'seo_title' => fake()->sentence(),
                'seo_description' => fake()->text(160),
            ],
            'status' => 'draft',
            'order' => 0,
            'is_current_version' => true,
            'version' => 1,
        ];
    }

    /**
     * State: Published content.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * State: Archived content.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    /**
     * State: Type section.
     */
    public function section(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'section',
            'slug' => null,
        ]);
    }

    /**
     * State: Type component.
     */
    public function component(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'component',
            'slug' => null,
        ]);
    }

    /**
     * State: Type block.
     */
    public function block(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'block',
            'slug' => null,
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
