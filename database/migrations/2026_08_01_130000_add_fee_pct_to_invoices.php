<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The management fee, on the document, as a percentage — like the tax.
 *
 * It was only ever a per-line markup inside the budget, so an invoice built
 * from the price list charged the raw services and quietly left the fee off.
 * Naming it on the invoice is what stops it being forgotten, and showing it as
 * its own row is what a client expects to see rather than a fee smeared
 * invisibly across every line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->float('fee_pct')->default(0)->after('tax_pct');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('fee_pct');
        });
    }
};
