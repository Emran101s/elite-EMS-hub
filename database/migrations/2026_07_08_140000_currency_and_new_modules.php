<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('budget_cents');
        });

        Schema::create('event_speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();          // job title
            $table->string('organization')->nullable();
            $table->string('topic')->nullable();           // talk / session title
            $table->text('bio')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('is_keynote')->default(false);
            $table->string('status')->default('invited');  // invited|confirmed|declined|cancelled
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_exhibitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('company');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('booth_number')->nullable();
            $table->string('booth_size')->nullable();       // e.g. 3x3 m
            $table->string('package')->default('standard'); // standard|premium|island|custom
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedInteger('paid_cents')->default(0);
            $table->string('status')->default('reserved');  // reserved|confirmed|paid|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('event_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('shuttle');     // shuttle|coach|sedan|van|vip|flight
            $table->string('route');                         // e.g. Airport → Hotel
            $table->string('provider')->nullable();
            $table->dateTime('depart_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('passengers')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->string('status')->default('planned');    // planned|booked|confirmed|completed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('event_accommodations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('hotel');
            $table->string('guest')->nullable();             // guest / group name
            $table->string('room_type')->nullable();
            $table->unsignedInteger('rooms')->default(1);
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->unsignedInteger('rate_cents')->default(0); // per room per night
            $table->unsignedInteger('cost_cents')->default(0); // total
            $table->string('status')->default('held');        // held|booked|confirmed|cancelled
            $table->string('confirmation_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_accommodations');
        Schema::dropIfExists('event_transport');
        Schema::dropIfExists('event_exhibitors');
        Schema::dropIfExists('event_speakers');
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('currency'));
    }
};
