<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venues become real location records the platform reuses across events: a type
 * (hotel, conference centre…), a street address, and a contact to call. Rooms
 * inside an event stay per-event in the Venue & Rooms hub tab — this is the flat
 * library of places, not their internal spaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('type', 40)->nullable()->after('name');
            $table->string('address', 240)->nullable()->after('type');
            $table->string('contact_name', 120)->nullable()->after('capacity');
            $table->string('contact_phone', 40)->nullable()->after('contact_name');
            $table->string('contact_email', 160)->nullable()->after('contact_phone');

            // A venue only truly needs a name; city/country were NOT NULL, which
            // would crash a save that leaves them blank.
            $table->string('city', 120)->nullable()->change();
            $table->string('country', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['type', 'address', 'contact_name', 'contact_phone', 'contact_email']);
        });
    }
};
