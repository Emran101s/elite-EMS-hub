<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The management fee on the offer, in the same shape as on the invoice.
 *
 * A proposal priced from the price list quotes raw services; quoting them
 * without the fee and adding it at contract time is how a client comes to feel
 * the price moved. It is on the offer, on its own row, from the start.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->float('fee_pct')->default(0)->after('tax_pct');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('fee_pct');
        });
    }
};
