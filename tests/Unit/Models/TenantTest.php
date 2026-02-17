<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestUsers;

/**
 * Testy jednostkowe dla modelu Tenant.
 */
class TenantTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    /**
     * Test: Tenant może zostać utworzony.
     */
    public function test_tenant_can_be_created(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'plan' => 'starter',
        ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'plan' => 'starter',
        ]);
        $this->assertNotNull($tenant->id);
        $this->assertTrue($tenant->is_active);
    }

    /**
     * Test: Tenant ma przypisanych użytkowników.
     */
    public function test_tenant_has_users(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $user = $this->createUserForTenant($tenant);

        $this->assertCount(1, $tenant->fresh()->users);
        $this->assertEquals($user->id, $tenant->users->first()->id);
    }

    /**
     * Test: Tenant ma domyślne wartości.
     */
    public function test_tenant_has_default_values(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->assertEquals('starter', $tenant->plan);
        $this->assertEquals('shared', $tenant->database_type);
        $this->assertTrue($tenant->is_active);
        $this->assertIsArray($tenant->settings);
    }

    /**
     * Test: Sprawdzenie czy tenant jest enterprise.
     */
    public function test_tenant_is_enterprise(): void
    {
        $starter = Tenant::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'plan' => 'starter',
        ]);

        $enterprise = Tenant::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'plan' => 'enterprise',
        ]);

        $this->assertFalse($starter->isEnterprise());
        $this->assertTrue($enterprise->isEnterprise());
    }

    /**
     * Test: Ustawienia JSON działają poprawnie.
     */
    public function test_tenant_settings_work(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $tenant->setSetting('theme.color', '#FF0000');
        $tenant->save();

        $tenant->refresh();

        $this->assertEquals('#FF0000', $tenant->getSetting('theme.color'));
        $this->assertNull($tenant->getSetting('nonexistent'));
        $this->assertEquals('default', $tenant->getSetting('nonexistent', 'default'));
    }
}
