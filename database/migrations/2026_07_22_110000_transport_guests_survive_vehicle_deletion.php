<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a vehicle must not delete the people. The guests were imported from a
 * flight list and still need a transfer — they go back to the pool instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->dropForeign(['transport_id']);
            $table->foreign('transport_id')->references('id')->on('event_transport')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->dropForeign(['transport_id']);
            $table->foreign('transport_id')->references('id')->on('event_transport')->cascadeOnDelete();
        });
    }
};
