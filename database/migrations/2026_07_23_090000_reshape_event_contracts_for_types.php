<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the Contract Generator: one contract per event becomes many, typed.
 *
 * The table keeps its name (payments already point at it) but gains a `type`, a
 * polymorphic counterparty, a language, and a title. The single existing contract
 * migrates in as a bilingual client agreement with its `data` untouched — no
 * client loses their agreement, the editor reopens on the same content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_contracts', function (Blueprint $table) {
            $table->string('type', 20)->default('client')->after('event_id');
            // The counterparty: a Supplier, EventSpeaker or EventSponsor — null for
            // client contracts (parties live in `data`) and free-text letters.
            $table->string('party_type')->nullable()->after('type');
            $table->unsignedBigInteger('party_id')->nullable()->after('party_type');
            $table->string('title')->nullable()->after('party_id');
            $table->string('language', 12)->default('en')->after('title');
            $table->string('template_key')->nullable()->after('language');

            $table->index(['event_id', 'type']);
            $table->index(['party_type', 'party_id']);
        });

        // The one existing contract is the client MSA — bilingual, as it always was.
        DB::table('event_contracts')->update(['type' => 'client', 'language' => 'bilingual']);
    }

    public function down(): void
    {
        Schema::table('event_contracts', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'type']);
            $table->dropIndex(['party_type', 'party_id']);
            $table->dropColumn(['type', 'party_type', 'party_id', 'title', 'language', 'template_key']);
        });
    }
};
