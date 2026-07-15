<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_plan_item_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_item_id')->constrained('event_plan_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['plan_item_id', 'user_id']);
        });

        // Carry existing single assignees over as the first owner.
        $rows = DB::table('event_plan_items')
            ->whereNotNull('assignee_id')
            ->get(['id', 'assignee_id']);

        foreach ($rows as $row) {
            DB::table('event_plan_item_user')->insertOrIgnore([
                'plan_item_id' => $row->id,
                'user_id' => $row->assignee_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_plan_item_user');
    }
};
