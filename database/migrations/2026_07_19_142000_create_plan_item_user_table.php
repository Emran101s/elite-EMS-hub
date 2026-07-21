<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plan Studio — an item can be owned by several people. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_item_user', function (Blueprint $table) {
            $table->foreignId('plan_item_id')->constrained('plan_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['plan_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_item_user');
    }
};
