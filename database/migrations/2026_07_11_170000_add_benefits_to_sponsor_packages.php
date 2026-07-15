<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sponsor_packages', function (Blueprint $table) {
            $table->string('blurb')->nullable()->after('price_cents');
            $table->json('benefits')->nullable()->after('blurb');
        });
    }

    public function down(): void
    {
        Schema::table('event_sponsor_packages', function (Blueprint $table) {
            $table->dropColumn(['blurb', 'benefits']);
        });
    }
};
