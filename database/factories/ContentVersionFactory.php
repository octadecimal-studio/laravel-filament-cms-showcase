<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Content\Models\ContentVersion;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory dla ContentVersion.
 *
 * @extends Factory<ContentVersion>
 */
final class ContentVersionFactory extends Factory
{
    protected $model = ContentVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => 1,
            'is_current' => true,
            'data' => [
                'title' => fake()->sentence(),
                'content' => fake()->paragraph(),
            ],
            'changes' => null,
            'change_summary' => 'Initial version',
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
