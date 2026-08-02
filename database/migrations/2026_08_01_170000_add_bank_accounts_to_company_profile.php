<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the money is supposed to be sent.
 *
 * An invoice that does not say how to pay it is an invoice somebody has to
 * reply to before they can pay it. The details lived in a Word template
 * outside the platform, so every invoice raised here went out without them.
 *
 * A list rather than columns, because there are two accounts today — one per
 * currency — and a third is a business decision, not a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->json('bank_accounts')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', fn (Blueprint $table) => $table->dropColumn('bank_accounts'));
    }
};
