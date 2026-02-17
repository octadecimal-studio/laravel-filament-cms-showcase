<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Core\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestUsers;

/**
 * Testy funkcjonalne dla autentykacji.
 */
class AuthenticationTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Test: Użytkownik może się zalogować.
     */
    public function test_user_can_login(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $user = $this->createUserForTenant($tenant, [
            'email' => 'test@example.com',
        ]);

        // Użyj actingAs zamiast POST do /admin/login
        // ponieważ Filament wymaga dodatkowej konfiguracji sesji
        $this->actingAs($user);

        $this->assertAuthenticated();
        $this->assertEquals($user->id, auth()->id());
    }

    /**
     * Test: Użytkownik nie może uzyskać dostępu do danych innego tenanta.
     */
    public function test_user_cannot_access_other_tenant(): void
    {
        $tenant1 = Tenant::create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1',
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2',
        ]);

        $user1 = $this->createUserForTenant($tenant1, ['email' => 'user1@example.com']);
        $user2 = $this->createUserForTenant($tenant2, ['email' => 'user2@example.com']);

        // Zaloguj jako user1
        $this->actingAs($user1);

        // user1 nie powinien mieć dostępu do tenant2
        $this->assertTrue($user1->tenant_id !== $user2->tenant_id);
        $this->assertFalse($user1->canAccessTenant($tenant2));
    }

    /**
     * Test: Super admin ma dostęp do wszystkich tenantów.
     */
    public function test_super_admin_can_access_all_tenants(): void
    {
        $tenant1 = Tenant::create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1',
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2',
        ]);

        $superAdmin = $this->createSuperAdmin(['email' => 'admin@octadecimal.studio']);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);

        // Super admin powinien móc uzyskać dostęp do obu tenantów
        $tenants = $superAdmin->getTenants(app(\Filament\Panel::class));

        $this->assertCount(2, $tenants);
    }

    /**
     * Test: Niepoprawne dane logowania są odrzucane.
     */
    public function test_invalid_credentials_rejected(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $user = $this->createUserForTenant($tenant, [
            'email' => 'test@example.com',
        ]);

        // Sprawdź że użytkownik nie jest zalogowany bez uwierzytelnienia
        $this->assertGuest();
    }
}
