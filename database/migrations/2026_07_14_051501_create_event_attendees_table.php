<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();
            $table->string('job_title')->nullable();
            $table->string('ticket_type')->default('Delegate');
            $table->string('status')->default('registered'); // registered | confirmed | checked_in | cancelled
            $table->unsignedInteger('amount_cents')->default(0); // ticket fee
            $table->boolean('vip')->default(false);
            $table->string('dietary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->json('default_ticket_types')->nullable()->after('default_management_fee_pct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_profiles', fn (Blueprint $t) => $t->dropColumn('default_ticket_types'));
        Schema::dropIfExists('event_attendees');
    }
};
