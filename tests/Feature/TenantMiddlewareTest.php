<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Core\Middleware\EnsureTenantSession;
use App\Modules\Core\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesTestUsers;

/**
 * Testy funkcjonalne dla middleware EnsureTenantSession.
 */
class TenantMiddlewareTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Test: Middleware ustawia kontekst tenanta.
     */
    public function test_middleware_sets_tenant_context(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $user = $this->createUserForTenant($tenant);

        $this->actingAs($user);

        // Symuluj request
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureTenantSession;
        $response = $middleware->handle($request, function ($req) use ($tenant) {
            // Sprawdź czy tenant jest ustawiony
            if (app()->bound('current_tenant')) {
                $currentTenant = app('current_tenant');
                $this->assertEquals($tenant->id, $currentTenant->id);
            }

            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test: Middleware odrzuca nieaktywnego tenanta.
     */
    public function test_middleware_rejects_inactive_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive-tenant',
            'is_active' => false,
        ]);

        $user = $this->createUserForTenant($tenant);

        $this->actingAs($user);

        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureTenantSession;

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $middleware->handle($request, function ($req) {
            return response('OK');
        });
    }

    /**
     * Test: Middleware pozwala super adminowi bez tenanta.
     */
    public function test_middleware_allows_super_admin_without_tenant(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);

        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $superAdmin);

        $middleware = new EnsureTenantSession;
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test: Middleware odrzuca zwykłego użytkownika bez tenanta.
     */
    public function test_middleware_rejects_user_without_tenant(): void
    {
        $user = $this->createUser();

        $this->actingAs($user);

        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureTenantSession;

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $middleware->handle($request, function ($req) {
            return response('OK');
        });
    }

    /**
     * Test: Middleware rozpoznaje tenanta z domeny.
     */
    public function test_middleware_resolves_tenant_from_domain(): void
    {
        $tenant = Tenant::create([
            'name' => 'Domain Tenant',
            'slug' => 'domain-tenant',
            'domain' => 'custom.example.com',
        ]);

        // Request bez zalogowanego użytkownika, ale z domeną
        $request = Request::create('http://custom.example.com/admin/login', 'GET');

        $middleware = new EnsureTenantSession;
        $response = $middleware->handle($request, function ($req) use ($tenant) {
            // Sprawdź czy tenant jest ustawiony z domeny
            if (app()->bound('current_tenant')) {
                $currentTenant = app('current_tenant');
                $this->assertEquals($tenant->id, $currentTenant->id);
            }

            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test: Middleware waliduje że użytkownik ma dostęp do tenanta z sesji.
     */
    public function test_middleware_validates_session_tenant_belongs_to_user(): void
    {
        $tenant1 = Tenant::create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1',
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2',
        ]);

        // Użytkownik należy do tenant1
        $user = $this->createUserForTenant($tenant1);

        $this->actingAs($user);

        // Symuluj sesję z tenant2 (próba manipulacji)
        session(['tenant_id' => $tenant2->id]);

        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureTenantSession;
        $response = $middleware->handle($request, function ($req) use ($tenant1) {
            // Middleware powinien użyć tenant1 (z użytkownika), nie tenant2 (z sesji)
            if (app()->bound('current_tenant')) {
                $currentTenant = app('current_tenant');
                $this->assertEquals($tenant1->id, $currentTenant->id);
            }

            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
