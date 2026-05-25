<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('rental.tables.availability_slots', 'availability_slots');

        Schema::table($table, function (Blueprint $t) {
            $t->string('google_calendar_event_id')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        $table = config('rental.tables.availability_slots', 'availability_slots');

        Schema::table($table, function (Blueprint $t) {
            $t->dropColumn('google_calendar_event_id');
        });
    }
};
