<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sections this brief does not use.
 *
 * The twelve sections are the same on every event because a brief is a
 * standard document, but not every event has sponsors, an exhibition floor or
 * a branding programme — and a heading with nothing under it reads, to a
 * client, as something nobody bothered to fill in.
 *
 * Kept as a list of removed keys rather than deleting the content: taking a
 * section off the document should not throw away what was written in it, and
 * putting it back should return what was there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_briefs', function (Blueprint $table) {
            $table->json('hidden_sections')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('event_briefs', fn (Blueprint $table) => $table->dropColumn('hidden_sections'));
    }
};
