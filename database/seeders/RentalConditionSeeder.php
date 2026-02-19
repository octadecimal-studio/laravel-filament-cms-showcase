<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RentalConditionSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo-studio')->first()
            ?? Tenant::first();

        if (! $tenant) {
            $this->command->error('Brak tenanta. Uruchom TenantSeeder.');
            return;
        }

        app()->instance('current_tenant', $tenant);
        $tid = $tenant->id;
        $now = now()->toDateTimeString();

        $this->command->info('Seedowanie warunków wypożyczenia...');

        $conditions = [
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tid,
                'title' => 'Wymagane dokumenty',
                'description' => '<p>Do wypożyczenia motocykla wymagane są:</p><ul><li>Prawo jazdy kategorii odpowiadającej pojemności motocykla (A1, A2 lub A)</li><li>Dowód osobisty lub paszport</li><li>Drugie potwierdzenie tożsamości (np. karta płatnicza z imieniem)</li></ul>',
                'icon' => 'heroicon-o-identification',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tid,
                'title' => 'Kaucja',
                'description' => '<p>Przy odbiorze motocykla pobieramy kaucję zwrotną:</p><ul><li>Kaucja zależy od modelu motocykla (od 500 zł do 10 000 zł)</li><li>Kaucja jest zwracana w pełni po oddaniu motocykla w stanie niepogorszonym</li><li>Akceptujemy płatności kartą lub gotówką</li></ul>',
                'icon' => 'heroicon-o-banknotes',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tid,
                'title' => 'Ubezpieczenie',
                'description' => '<p>Wszystkie motocykle w naszej flocie są w pełni ubezpieczone:</p><ul><li>OC (odpowiedzialność cywilna) — obowiązkowe</li><li>AC (autocasco) — opcjonalne, zmniejsza odpowiedzialność finansową</li><li>NNW (następstwa nieszczęśliwych wypadków) — w cenie wypożyczenia</li></ul>',
                'icon' => 'heroicon-o-shield-check',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($conditions as $c) {
            DB::table('two_wheels_rental_conditions')->insert($c);
        }

        $this->command->info('  Rental Conditions: ' . count($conditions));
    }
}
