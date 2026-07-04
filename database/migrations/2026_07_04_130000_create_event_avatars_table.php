<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_avatars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // drives the built-in SVG component
            $table->string('subtitle');
            $table->string('category'); // conference | gala | exhibition | workshop | vip | festival
            $table->string('best_for');
            $table->string('image_path')->nullable(); // uploaded render; SVG fallback when null
            $table->string('thumbnail_path')->nullable();
            $table->string('model_3d_path')->nullable(); // GLB/USDZ for future 3D hub mode
            $table->boolean('supports_3d')->default(false);
            $table->json('colors'); // palette swatches
            $table->json('recommended_types'); // subset of Event::TYPES
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('avatar_id')->nullable()->after('project_id')
                ->constrained('event_avatars')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_id');
        });

        Schema::dropIfExists('event_avatars');
    }
};
