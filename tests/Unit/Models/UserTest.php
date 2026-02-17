<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testy jednostkowe dla modelu User.
 */
class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper do tworzenia użytkownika z tenantem.
     */
    private function createUserWithTenant(Tenant $tenant, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ], $attributes));

        // tenant_id chronione przed mass assignment
        $user->tenant_id = $tenant->id;
        $user->save();

        return $user;
    }

    /**
     * Helper do tworzenia super admina.
     */
    private function createSuperAdmin(array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ], $attributes));

        // is_super_admin chronione przed mass assignment
        $user->is_super_admin = true;
        $user->save();

        return $user;
    }

    /**
     * Test: Użytkownik należy do tenanta.
     */
    public function test_user_belongs_to_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $user = $this->createUserWithTenant($tenant);

        $this->assertEquals($tenant->id, $user->tenant_id);
        $this->assertNotNull($user->tenant);
        $this->assertEquals($tenant->name, $user->tenant->name);
    }

    /**
     * Test: Super admin nie ma przypisanego tenanta.
     */
    public function test_super_admin_has_no_tenant(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->assertTrue($superAdmin->is_super_admin);
        $this->assertNull($superAdmin->tenant_id);
        $this->assertNull($superAdmin->tenant);
    }

    /**
     * Test: Sprawdzenie metody isSuperAdmin.
     */
    public function test_is_super_admin_method(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $regularUser = $this->createUserWithTenant($tenant, ['email' => 'user@example.com']);
        $superAdmin = $this->createSuperAdmin(['email' => 'admin@example.com']);

        $this->assertFalse($regularUser->isSuperAdmin());
        $this->assertTrue($superAdmin->isSuperAdmin());
    }

    /**
     * Test: Użytkownik używa UUID.
     */
    public function test_user_uses_uuid(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // UUID powinno być 36-znakowym stringiem
        $this->assertIsString($user->id);
        $this->assertEquals(36, strlen($user->id));
    }

    /**
     * Test: tenant_id nie może być ustawiony przez mass assignment.
     */
    public function test_tenant_id_protected_from_mass_assignment(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        // Próba ustawienia tenant_id przez mass assignment
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id, // To powinno być zignorowane
        ]);

        // tenant_id powinien być null (nie został ustawiony)
        $this->assertNull($user->tenant_id);
    }

    /**
     * Test: is_super_admin nie może być ustawiony przez mass assignment.
     */
    public function test_is_super_admin_protected_from_mass_assignment(): void
    {
        // Próba ustawienia is_super_admin przez mass assignment
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'is_super_admin' => true, // To powinno być zignorowane
        ]);

        // is_super_admin powinien być false (domyślna wartość)
        $this->assertFalse($user->is_super_admin);
    }

    /**
     * Test: canAccessTenant odrzuca nieaktywnych tenantów dla super admina.
     *
     * BEZPIECZEŃSTWO: Super admin ma dostęp tylko do AKTYWNYCH tenantów.
     */
    public function test_super_admin_cannot_access_inactive_tenant(): void
    {
        $inactiveTenant = Tenant::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive-tenant',
            'is_active' => false,
        ]);

        $superAdmin = $this->createSuperAdmin();

        // Super admin NIE powinien mieć dostępu do nieaktywnego tenanta
        $this->assertFalse($superAdmin->canAccessTenant($inactiveTenant));
    }

    /**
     * Test: canAccessTenant odrzuca modele które nie są instancją Tenant.
     *
     * BEZPIECZEŃSTWO: Metoda wymaga instancji Tenant, inne typy są odrzucane.
     */
    public function test_can_access_tenant_rejects_non_tenant_models(): void
    {
        $superAdmin = $this->createSuperAdmin();

        // Przekazanie innego modelu (User) powinno zwrócić false
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => 'password',
        ]);

        // canAccessTenant powinno odrzucić model który nie jest Tenant
        $this->assertFalse($superAdmin->canAccessTenant($otherUser));
    }

    /**
     * Test: canAccessTenant działa poprawnie dla aktywnego tenanta.
     */
    public function test_can_access_tenant_allows_active_tenant(): void
    {
        $activeTenant = Tenant::create([
            'name' => 'Active Tenant',
            'slug' => 'active-tenant',
            'is_active' => true,
        ]);

        $superAdmin = $this->createSuperAdmin();
        $regularUser = $this->createUserWithTenant($activeTenant, ['email' => 'user@example.com']);

        // Super admin ma dostęp do aktywnego tenanta
        $this->assertTrue($superAdmin->canAccessTenant($activeTenant));

        // Zwykły użytkownik ma dostęp do swojego aktywnego tenanta
        $this->assertTrue($regularUser->canAccessTenant($activeTenant));
    }

    /**
     * Test: canAccessTenant odrzuca cudzy tenant dla zwykłego użytkownika.
     */
    public function test_regular_user_cannot_access_other_tenant(): void
    {
        $tenant1 = Tenant::create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1',
            'is_active' => true,
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2',
            'is_active' => true,
        ]);

        $user = $this->createUserWithTenant($tenant1, ['email' => 'user@example.com']);

        // Użytkownik ma dostęp do swojego tenanta
        $this->assertTrue($user->canAccessTenant($tenant1));

        // Użytkownik NIE ma dostępu do cudzego tenanta
        $this->assertFalse($user->canAccessTenant($tenant2));
    }
}
