<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public registration.
 *
 * Attendees could only be typed in or imported from a spreadsheet, which means
 * somebody was retyping a form somebody else had already filled in. An event
 * can now publish a link.
 *
 * The link carries a token rather than the event id. An id is guessable, and a
 * registration page is the one part of the platform a stranger is meant to
 * reach — so the URL should not also tell them how many events you run or
 * invite them to walk the others.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('registration_token', 32)->nullable()->unique()->after('progress');
            $table->boolean('registration_open')->default(false)->after('registration_token');
            // Null means no cap. Zero would mean "nobody", which is what the
            // open switch is for, so it is deliberately not the same thing.
            $table->unsignedInteger('registration_capacity')->nullable()->after('registration_open');
            $table->text('registration_note')->nullable()->after('registration_capacity');
        });

        // Every existing event gets a token now rather than lazily, so the link
        // is stable from the first time anyone goes looking for it.
        foreach (DB::table('events')->pluck('id') as $id) {
            DB::table('events')->where('id', $id)
                ->update(['registration_token' => Str::lower(Str::random(24))]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'registration_token', 'registration_open',
                'registration_capacity', 'registration_note',
            ]);
        });
    }
};
