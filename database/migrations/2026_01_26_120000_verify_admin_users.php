<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ustawia email_verified_at dla kont super adminów (jeszcze niezweryfikowanych).
 * Dzięki temu mogą logować się do panelu bez weryfikacji email.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();
        DB::table('users')
            ->where('is_super_admin', true)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => $now]);
    }

    public function down(): void
    {
        // Nie cofamy – weryfikacja nie powinna być odwoływana przez rollback
    }
};
