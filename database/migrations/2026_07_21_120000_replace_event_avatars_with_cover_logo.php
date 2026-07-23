<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events carry their own cover image + logo instead of picking from a shared
 * avatar library. Drops the event_avatars table and events.avatar_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('project_id');
            $table->string('logo_path')->nullable()->after('cover_path');
        });

        if (Schema::hasColumn('events', 'avatar_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropConstrainedForeignId('avatar_id');
            });
        }

        Schema::dropIfExists('event_avatars');
    }

    public function down(): void
    {
        // Minimal restore so the migration is reversible.
        Schema::create('event_avatars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('category')->nullable();
            $table->string('best_for')->nullable();
            $table->string('image_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('model_3d_path')->nullable();
            $table->boolean('supports_3d')->default(false);
            $table->json('colors')->nullable();
            $table->json('recommended_types')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('avatar_id')->nullable()->after('project_id')
                ->constrained('event_avatars')->nullOnDelete();
            $table->dropColumn(['cover_path', 'logo_path']);
        });
    }
};
