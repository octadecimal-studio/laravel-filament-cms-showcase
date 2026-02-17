<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Modules\Content\Models\ContentBlock;
use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testy jednostkowe dla ContentBlock.
 */
final class ContentBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_block_can_be_created(): void
    {
        $tenant = Tenant::factory()->create();
        $block = ContentBlock::factory()->forTenant($tenant)->create();

        $this->assertDatabaseHas('content_blocks', [
            'id' => $block->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_content_block_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $block = ContentBlock::factory()->forTenant($tenant)->create();

        $this->assertInstanceOf(Tenant::class, $block->tenant);
        $this->assertEquals($tenant->id, $block->tenant->id);
    }

    public function test_active_scope_filters_correctly(): void
    {
        $tenant = Tenant::factory()->create();

        ContentBlock::factory()->forTenant($tenant)->create();
        ContentBlock::factory()->forTenant($tenant)->inactive()->create();

        // Wyłącz TenantScope dla testu
        $active = ContentBlock::withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class)
            ->active()
            ->count();

        $this->assertEquals(1, $active);
    }

    public function test_usage_count_can_be_incremented(): void
    {
        $tenant = Tenant::factory()->create();
        $block = ContentBlock::factory()->forTenant($tenant)->create(['usage_count' => 5]);

        $block->incrementUsage();

        $this->assertEquals(6, $block->fresh()->usage_count);
    }

    public function test_usage_count_can_be_decremented(): void
    {
        $tenant = Tenant::factory()->create();
        $block = ContentBlock::factory()->forTenant($tenant)->create(['usage_count' => 5]);

        $block->decrementUsage();

        $this->assertEquals(4, $block->fresh()->usage_count);
    }

    public function test_block_has_json_schema(): void
    {
        $tenant = Tenant::factory()->create();
        $block = ContentBlock::factory()->forTenant($tenant)->create([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                ],
            ],
        ]);

        $this->assertIsArray($block->schema);
        $this->assertEquals('object', $block->schema['type']);
    }
}
