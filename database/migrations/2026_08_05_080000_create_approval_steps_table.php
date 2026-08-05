<?php

use App\Models\EventApproval;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('event_approvals')->cascadeOnDelete();
            $table->unsignedInteger('position');
            // Optional human label for what this step is ("Finance", "Ops Director") —
            // separate from who decides it, since a step can name a role without a person yet.
            $table->string('label')->nullable();
            // Null means "any manager", same as the platform's behavior before chains existed.
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['approval_id', 'position']);
        });

        // Every approval, including the ones already in the book, is a chain —
        // just a chain of one until somebody adds a second step. Backfilling a
        // single step per existing row means the app never has to special-case
        // "an approval with no steps yet."
        EventApproval::query()->each(function (EventApproval $approval) {
            $approval->steps()->create([
                'position' => 1,
                'status' => $approval->status,
                'decided_by' => $approval->decided_by,
                'decided_at' => $approval->decided_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
