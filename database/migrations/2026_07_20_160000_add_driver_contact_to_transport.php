<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who to call when the vehicle is not where it should be. Deliberately a
 * contact rather than a name — the number is what an operation actually uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->string('driver_contact', 60)->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->dropColumn('driver_contact');
        });
    }
};
