<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_plan_items', function (Blueprint $table) {
            $table->string('workstream')->default('strategy')->after('event_id');
            $table->text('description')->nullable()->after('title');
            $table->string('deadline_code')->nullable()->after('due_on');   // T-90, T-7, EVENT_DAY, T+7
            $table->string('owner_role')->nullable()->after('assignee_id'); // suggested owner role
            $table->boolean('approval_required')->default(false)->after('owner_role');
            $table->string('approval_status')->default('none')->after('approval_required'); // none|pending|approved|rejected
            $table->boolean('budget_impact')->default(false)->after('approval_status');
            $table->string('risk_level')->default('low')->after('budget_impact'); // low|medium|high|critical
            $table->json('dependencies')->nullable()->after('risk_level');    // template keys this task depends on
            $table->string('template_key')->nullable()->after('dependencies');
        });

        // Config used to generate the plan, stored on the event.
        Schema::table('events', function (Blueprint $table) {
            $table->json('planner_config')->nullable()->after('management_fee_pct');
        });
    }

    public function down(): void
    {
        Schema::table('event_plan_items', function (Blueprint $table) {
            $table->dropColumn(['workstream', 'description', 'deadline_code', 'owner_role', 'approval_required', 'approval_status', 'budget_impact', 'risk_level', 'dependencies', 'template_key']);
        });
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('planner_config'));
    }
};
