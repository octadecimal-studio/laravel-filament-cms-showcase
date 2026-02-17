<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory dla modelu Tenant.
 *
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Model powiązany z factory.
     *
     * @var class-string<Tenant>
     */
    protected $model = Tenant::class;

    /**
     * Definiuje domyślny stan modelu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'domain' => null,
            'plan' => fake()->randomElement(['starter', 'pro', 'enterprise']),
            'database_type' => 'shared',
            'database_name' => null,
            'settings' => [
                'locale' => 'pl',
                'timezone' => 'Europe/Warsaw',
            ],
            'is_active' => true,
        ];
    }

    /**
     * Wskazuje, że tenant jest nieaktywny.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Wskazuje, że tenant ma plan starter.
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'starter',
        ]);
    }

    /**
     * Wskazuje, że tenant ma plan pro.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'pro',
        ]);
    }

    /**
     * Wskazuje, że tenant ma plan enterprise z dedykowaną bazą.
     */
    public function enterprise(): static
    {
        return $this->state(function (array $attributes) {
            $slug = $attributes['slug'] ?? Str::random(10);

            return [
                'plan' => 'enterprise',
                'database_type' => 'dedicated',
                'database_name' => 'tenant_'.Str::slug($slug),
            ];
        });
    }

    /**
     * Ustawia własną domenę dla tenanta.
     */
    public function withDomain(string $domain): static
    {
        return $this->state(fn (array $attributes) => [
            'domain' => $domain,
        ]);
    }
}
