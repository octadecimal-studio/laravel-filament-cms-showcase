<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Modules\Content\Models\SiteContent;
use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testy jednostkowe dla SiteContent.
 */
final class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_content_can_be_created(): void
    {
        $tenant = Tenant::factory()->create();
        $content = SiteContent::factory()->forTenant($tenant)->create();

        $this->assertDatabaseHas('site_contents', [
            'id' => $content->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_site_content_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $content = SiteContent::factory()->forTenant($tenant)->create();

        $this->assertInstanceOf(Tenant::class, $content->tenant);
        $this->assertEquals($tenant->id, $content->tenant->id);
    }

    public function test_published_scope_filters_correctly(): void
    {
        $tenant = Tenant::factory()->create();

        SiteContent::factory()->forTenant($tenant)->published()->create();
        SiteContent::factory()->forTenant($tenant)->create(['status' => 'draft']);
        SiteContent::factory()->forTenant($tenant)->archived()->create();

        // Wyłącz TenantScope dla testu
        $published = SiteContent::withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class)
            ->published()
            ->count();

        $this->assertEquals(1, $published);
    }

    public function test_is_published_method_works(): void
    {
        $tenant = Tenant::factory()->create();

        $publishedContent = SiteContent::factory()
            ->forTenant($tenant)
            ->published()
            ->create();

        $draftContent = SiteContent::factory()
            ->forTenant($tenant)
            ->create(['status' => 'draft']);

        $this->assertTrue($publishedContent->isPublished());
        $this->assertFalse($draftContent->isPublished());
    }

    public function test_content_can_have_parent_child_relationship(): void
    {
        $tenant = Tenant::factory()->create();

        $parent = SiteContent::factory()->forTenant($tenant)->create();
        $child = SiteContent::factory()
            ->forTenant($tenant)
            ->section()
            ->create(['parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_different_content_types_work(): void
    {
        $tenant = Tenant::factory()->create();

        $page = SiteContent::factory()->forTenant($tenant)->create();
        $section = SiteContent::factory()->forTenant($tenant)->section()->create();
        $component = SiteContent::factory()->forTenant($tenant)->component()->create();
        $block = SiteContent::factory()->forTenant($tenant)->block()->create();

        $this->assertEquals('page', $page->type);
        $this->assertEquals('section', $section->type);
        $this->assertEquals('component', $component->type);
        $this->assertEquals('block', $block->type);
    }
}
