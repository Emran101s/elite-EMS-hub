<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much an event matters, separately from how it is going.
 *
 * The stage says where an event is in its life and health says whether it is
 * in trouble; neither says whether it is the one to protect when two of them
 * want the same crew on the same weekend. That is a decision a person makes,
 * so it is stored rather than derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('priority')->default('normal')->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('priority'));
    }
};
