<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // conference | gala | workshop | exhibition | career_fair | dinner
            $table->string('status')->default('planning'); // planning | on_track | in_progress | at_risk | behind | completed
            $table->string('city');
            $table->string('country');
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedBigInteger('budget_cents')->default(0);
            $table->unsignedTinyInteger('progress')->default(0); // 0–100
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
