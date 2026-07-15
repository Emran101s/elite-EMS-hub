<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_plan_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });

        Schema::table('event_plan_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('event_id')->constrained('event_plan_categories')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('category_id')->constrained('event_plan_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('event_plan_categories');
    }
};
