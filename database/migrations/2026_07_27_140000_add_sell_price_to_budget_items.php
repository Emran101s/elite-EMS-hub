<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each line costs, and what it is charged at.
 *
 * The budget only ever knew cost — estimated, actual, approved, paid — and the
 * client's side of the deal lived somewhere else entirely: a contract value, a
 * flat management fee on the subtotal, some income rows. So the one question
 * an events business asks about every line ("this cost me X, what am I
 * invoicing for it?") could not be answered at all.
 *
 * Three columns answer it:
 *
 *   sell_cents   what the client is charged for this line. Null means "work it
 *                out from the fee" — so an event nobody has priced by hand
 *                still totals exactly what it totalled before.
 *   markup_pct   an alternative way to say the same thing: charge cost plus
 *                this much. Kept as well as sell_cents because people think
 *                both ways — some lines are "cost + 20%", some are "we quoted
 *                12,000 for this whatever it costs us".
 *   billable     off for a line you absorb. It still costs, it just is not on
 *                the client's invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->unsignedBigInteger('sell_cents')->nullable()->after('approved_cents');
            $table->decimal('markup_pct', 6, 2)->nullable()->after('sell_cents');
            $table->boolean('billable')->default(true)->after('markup_pct');
        });
    }

    public function down(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->dropColumn(['sell_cents', 'markup_pct', 'billable']);
        });
    }
};
