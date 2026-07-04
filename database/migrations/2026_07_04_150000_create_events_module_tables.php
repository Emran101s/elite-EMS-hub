<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->after('client_id')->constrained('users')->nullOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->unsignedInteger('expected_participants')->nullable()->after('progress');
            // Lifecycle stage; `status` keeps the health status (on_track / at_risk / …).
            $table->string('stage')->default('planning')->after('status');
            // Event color theme — null inherits the avatar palette.
            $table->string('primary_color', 9)->nullable();
            $table->string('secondary_color', 9)->nullable();
            $table->string('accent_color', 9)->nullable();
            $table->string('text_color', 9)->nullable();
        });

        Schema::create('event_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('breakout'); // main_hall | breakout | exhibition | registration | vip | catering
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();
        });

        Schema::create('event_agenda_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('label'); // "Day 1 — Opening"
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('event_agenda_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_day_id')->constrained('event_agenda_days')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('event_rooms')->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('keynote'); // opening | keynote | panel | workshop | break | lunch | networking | exhibition | gala_dinner | closing
            $table->string('status')->default('draft'); // draft | confirmed | waiting_speaker | needs_review | final
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('speaker')->nullable();
            $table->string('moderator')->nullable();
            $table->string('track')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['event_id', 'agenda_day_id']);
        });

        Schema::create('event_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // venue | catering | av | production | branding | transportation | printing | misc | …
            $table->string('description')->nullable();
            $table->unsignedBigInteger('estimated_cents')->default(0);
            $table->unsignedBigInteger('actual_cents')->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_status')->default('pending'); // pending | partial | paid
            $table->string('invoice_number')->nullable();
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('event_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('package'); // platinum | gold | silver | bronze | strategic | supporting
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('payment_status')->default('pending'); // pending | partial | paid
            $table->string('booth')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('event_risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category'); // venue | supplier | budget | client_approval | speaker | logistics | production | weather | attendance | technical
            $table->unsignedTinyInteger('probability'); // 1–5
            $table->unsignedTinyInteger('impact'); // 1–5
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('mitigation')->nullable();
            $table->string('status')->default('open'); // open | monitoring | mitigated | escalated | closed
            $table->date('due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('event_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type'); // budget | supplier | design | venue | agenda | client | payment | report
            $table->string('status')->default('pending'); // pending | approved | rejected | needs_revision
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('event_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // project_manager | operations_lead | registration_lead | supplier_coordinator | finance_owner | design_owner | production_owner | client_rm
            $table->unique(['event_id', 'user_id', 'role']);
        });

        Schema::table('event_supplier', function (Blueprint $table) {
            $table->string('status')->default('requested')->after('supplier_id'); // requested | quoted | approved | contracted | in_production | delivered | completed | issue
            $table->text('notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_supplier', function (Blueprint $table) {
            $table->dropColumn(['status', 'notes']);
        });

        Schema::dropIfExists('event_team_members');
        Schema::dropIfExists('event_approvals');
        Schema::dropIfExists('event_risks');
        Schema::dropIfExists('event_sponsors');
        Schema::dropIfExists('event_budget_items');
        Schema::dropIfExists('event_agenda_sessions');
        Schema::dropIfExists('event_agenda_days');
        Schema::dropIfExists('event_rooms');

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('project_manager_id');
            $table->dropColumn(['description', 'expected_participants', 'stage', 'primary_color', 'secondary_color', 'accent_color', 'text_color']);
        });

        Schema::dropIfExists('clients');
    }
};
