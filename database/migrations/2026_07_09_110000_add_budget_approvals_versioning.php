<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('budget_status')->default('draft')->after('planner_config'); // draft|pending|approved
            $table->timestamp('budget_locked_at')->nullable()->after('budget_status');
        });

        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_cents')->default(0)->after('estimated_cents'); // locked baseline
        });

        Schema::create('event_budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('label')->nullable();
            $table->string('status')->default('draft'); // pending|approved|rejected|superseded
            $table->text('note')->nullable();
            $table->json('snapshot')->nullable();          // line-level snapshot at submission
            $table->json('totals')->nullable();            // estimated / fee / grand / income / net
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_budget_versions');
        Schema::table('event_budget_items', fn (Blueprint $table) => $table->dropColumn('approved_cents'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['budget_status', 'budget_locked_at']));
    }
};
