<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;
use App\Modules\Core\Models\Tenant;

/**
 * Trait pomocniczy do tworzenia użytkowników w testach.
 *
 * Używaj tego traitu zamiast bezpośredniego User::create() z tenant_id,
 * ponieważ tenant_id i is_super_admin są chronione przed mass assignment.
 */
trait CreatesTestUsers
{
    /**
     * Tworzy użytkownika z przypisanym tenantem.
     */
    protected function createUserForTenant(Tenant $tenant, array $attributes = []): User
    {
        // Zachowaj email_verified_at z domyślnych ustawień jeśli nie podano jawnie
        $emailVerified = array_key_exists('email_verified_at', $attributes)
            ? $attributes['email_verified_at']
            : now();

        $defaults = [
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => 'password',
        ];

        $merged = array_merge($defaults, $attributes);
        $merged['email_verified_at'] = $emailVerified;

        $user = User::create($merged);

        $user->tenant_id = $tenant->id;
        $user->save();

        return $user->fresh();
    }

    /**
     * Tworzy super admina.
     */
    protected function createSuperAdmin(array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Super Admin',
            'email' => 'admin'.uniqid().'@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ], $attributes));

        $user->is_super_admin = true;
        $user->save();

        return $user->fresh();
    }

    /**
     * Tworzy zwykłego użytkownika bez tenanta.
     */
    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ], $attributes));
    }
}
