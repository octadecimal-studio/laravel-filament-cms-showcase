<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Core\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Komenda do tworzenia użytkowników admin (super admin lub tenant admin).
 *
 * Użycie:
 * - Super admin: php artisan admin:create --super
 * - Tenant admin: php artisan admin:create --tenant=demo-studio
 */
class CreateAdminUser extends Command
{
    /**
     * Sygnatura komendy.
     *
     * @var string
     */
    protected $signature = 'admin:create
                            {--super : Utworz super admina (bez tenanta)}
                            {--tenant= : Slug tenanta dla tenant admina}
                            {--email= : Email użytkownika}
                            {--name= : Imię użytkownika}
                            {--password= : Hasło (opcjonalne, domyślnie: password)}';

    /**
     * Opis komendy.
     *
     * @var string
     */
    protected $description = 'Utworz użytkownika admin (super admin lub tenant admin)';

    /**
     * Wykonaj komendę.
     */
    public function handle(): int
    {
        $isSuper = $this->option('super');
        $tenantSlug = $this->option('tenant');

        // Walidacja opcji
        if (! $isSuper && ! $tenantSlug) {
            $this->error('Musisz podać --super lub --tenant=slug');
            $this->info('Przykłady:');
            $this->line('  php artisan admin:create --super --email=admin@example.com --name="Super Admin"');
            $this->line('  php artisan admin:create --tenant=demo-studio --email=admin@demo.com --name="Demo Admin"');

            return self::FAILURE;
        }

        if ($isSuper && $tenantSlug) {
            $this->error('Nie możesz użyć --super i --tenant jednocześnie');

            return self::FAILURE;
        }

        // Pobierz dane od użytkownika
        $email = $this->option('email') ?? $this->ask('Email');
        $name = $this->option('name') ?? $this->ask('Imię i nazwisko');
        $password = $this->option('password') ?? $this->secret('Hasło (domyślnie: password)') ?? 'password';

        // Walidacja email
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            $this->error('Błąd walidacji:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        // Dla tenant admina - sprawdź czy tenant istnieje
        $tenant = null;
        if ($tenantSlug) {
            assert(is_string($tenantSlug), 'Tenant slug must be a string');

            $tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->first();

            if (! $tenant) {
                $this->error("Tenant '{$tenantSlug}' nie istnieje lub jest nieaktywny");
                $this->info('Dostępne tenanty:');

                foreach (Tenant::where('is_active', true)->get() as $t) {
                    $this->line("  - {$t->slug} ({$t->name})");
                }

                return self::FAILURE;
            }
        }

        // Utwórz użytkownika
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        // Ustaw pola chronione
        if ($isSuper) {
            $user->is_super_admin = true;
            // Przypisz do system tenant (Tenant 0)
            $systemTenant = \App\Modules\Core\Models\Tenant::getSystemTenant();
            if (!$systemTenant) {
                // Jeśli system tenant nie istnieje, utwórz go
                $systemTenant = \App\Modules\Core\Models\Tenant::create([
                    'id' => \App\Modules\Core\Models\Tenant::SYSTEM_TENANT_ID,
                    'name' => 'System (Super Admini)',
                    'slug' => 'system',
                    'plan' => 'enterprise',
                    'database_type' => 'shared',
                    'settings' => ['is_system' => true],
                    'is_active' => true,
                ]);
            }
            $user->tenant_id = $systemTenant->id;
            $user->save();
            $user->assignRole('super_admin');

            $this->info('✅ Super admin utworzony pomyślnie!');
            $this->newLine();
            $this->line("Email: {$email}");
            $this->line("Hasło: {$password}");
            $this->line('Rola: super_admin');
            $this->line('Tenant: brak (dostęp do wszystkich)');
        } else {
            assert($tenant !== null, 'Tenant must be set for tenant admin');

            $user->tenant_id = $tenant->id;
            $user->save();
            $user->assignRole('tenant_admin');

            $this->info('✅ Tenant admin utworzony pomyślnie!');
            $this->newLine();
            $this->line("Email: {$email}");
            $this->line("Hasło: {$password}");
            $this->line('Rola: tenant_admin');
            $this->line("Tenant: {$tenant->slug} ({$tenant->name})");
        }

        $this->newLine();
        $this->comment('Możesz teraz zalogować się do panelu: /admin');

        return self::SUCCESS;
    }
}
