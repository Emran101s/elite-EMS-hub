<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_exhibition_halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('width_m', 6, 2)->default(30);
            $table->decimal('length_m', 6, 2)->default(20);
            $table->unsignedInteger('position')->default(0);
            $table->json('fixtures')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });

        Schema::table('event_exhibitors', function (Blueprint $table) {
            // Replace the pixel floor-plan columns with metre-accurate, hall-scoped ones.
            $table->dropColumn(['pos_x', 'pos_y', 'booth_w', 'booth_h']);
        });

        Schema::table('event_exhibitors', function (Blueprint $table) {
            $table->foreignId('hall_id')->nullable()->after('event_id')->constrained('event_exhibition_halls')->nullOnDelete();
            $table->decimal('booth_x', 6, 2)->nullable()->after('booth_size'); // centre, metres
            $table->decimal('booth_y', 6, 2)->nullable()->after('booth_x');
            $table->decimal('booth_w_m', 5, 2)->nullable()->after('booth_y');
            $table->decimal('booth_h_m', 5, 2)->nullable()->after('booth_w_m');
        });
    }

    public function down(): void
    {
        Schema::table('event_exhibitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hall_id');
            $table->dropColumn(['booth_x', 'booth_y', 'booth_w_m', 'booth_h_m']);
            $table->integer('pos_x')->nullable();
            $table->integer('pos_y')->nullable();
            $table->unsignedInteger('booth_w')->nullable();
            $table->unsignedInteger('booth_h')->nullable();
        });

        Schema::dropIfExists('event_exhibition_halls');
    }
};
