<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_approvals', function (Blueprint $table) {
            // What the request is worth, when it has a figure — the thing
            // conditional routing reads to decide whether an extra step
            // belongs in the chain. Optional: most approval types (venue,
            // design, agenda…) never carry one.
            $table->unsignedBigInteger('amount_cents')->nullable()->after('notes');
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            // A step can be handed to a person (approver_id) or left open to
            // "any manager" (both null) — min_role adds a third shape: open
            // to anyone AT LEAST this senior, for a step nobody names in
            // advance but that policy still wants gated above the baseline.
            $table->string('min_role')->nullable()->after('approver_id');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            // Null means the policy is off — conditional routing never fires
            // until a house explicitly sets a figure, so switching this
            // migration on changes no existing event's behaviour.
            $table->unsignedBigInteger('approval_threshold_cents')->nullable()->after('default_management_fee_pct');
        });
    }

    public function down(): void
    {
        Schema::table('event_approvals', function (Blueprint $table) {
            $table->dropColumn('amount_cents');
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('min_role');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('approval_threshold_cents');
        });
    }
};
