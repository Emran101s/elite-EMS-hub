<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The badge design, per event.
 *
 * JSON on the event rather than its own table: it is one small settings object
 * that only the badge reads, and a table would buy nothing but a join. Every
 * key has a default in App\Support\Badge, so an event nobody has designed a
 * badge for still prints one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->json('badge_template')->nullable()->after('registration_note'));
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('badge_template'));
    }
};
