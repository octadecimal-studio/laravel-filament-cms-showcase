<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Migracja tworząca specjalnego Tenanta 0 (System) dla super adminów.
 */
return new class extends Migration
{
    /**
     * Uruchom migrację.
     */
    public function up(): void
    {
        // Sprawdź czy tenant systemowy już istnieje
        $systemTenantExists = DB::table('tenants')
            ->where('slug', 'system')
            ->exists();

        if (! $systemTenantExists) {
            // Utwórz specjalnego tenanta dla super adminów
            // Używamy stałego UUID dla łatwego identyfikowania
            $systemTenantId = '00000000-0000-0000-0000-000000000000';

            DB::table('tenants')->insert([
                'id' => $systemTenantId,
                'name' => 'System (Super Admini)',
                'slug' => 'system',
                'domain' => null,
                'plan' => 'enterprise',
                'database_type' => 'shared',
                'settings' => json_encode([
                    'locale' => 'pl',
                    'timezone' => 'Europe/Warsaw',
                    'is_system' => true,
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Zaktualizuj wszystkich super adminów aby mieli tenant_id = system tenant
            DB::table('users')
                ->where('is_super_admin', true)
                ->orWhereNull('tenant_id')
                ->update([
                    'tenant_id' => $systemTenantId,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Cofnij migrację.
     */
    public function down(): void
    {
        // Przywróć tenant_id = null dla super adminów
        $systemTenantId = '00000000-0000-0000-0000-000000000000';

        DB::table('users')
            ->where('tenant_id', $systemTenantId)
            ->where('is_super_admin', true)
            ->update([
                'tenant_id' => null,
                'updated_at' => now(),
            ]);

        // Usuń tenant systemowy
        DB::table('tenants')
            ->where('id', $systemTenantId)
            ->delete();
    }
};
