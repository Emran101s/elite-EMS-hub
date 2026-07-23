<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The people layer: a driver and a specific vehicle stop being free text on a
 * movement and become records of their own. That's what makes driver overload
 * detection, trip sheets and WhatsApp templates possible at all.
 *
 * Both are company-wide catalogues, like vehicle types — you hire the same
 * drivers and the same fleet across events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();   // often a different number
            $table->string('licence_no', 60)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('languages')->nullable();      // "Arabic, English"
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plate_no', 40)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model', 80)->nullable();      // "Mercedes V-Class"
            $table->string('colour', 40)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('features')->nullable();       // "wifi, water, child seat"
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('event_transport', function (Blueprint $table) {
            // Assignments are plans — deleting a driver must never delete a trip.
            $table->foreignId('driver_id')->nullable()->after('vehicle_type_id')->constrained('transport_drivers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->after('driver_id')->constrained('transport_vehicles')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('vehicle_id')->constrained()->nullOnDelete();

            $table->boolean('is_vip')->default(false)->after('leg');
            $table->dateTime('delayed_to')->nullable()->after('arrive_at');
            $table->text('issue_note')->nullable()->after('notes');
            $table->dateTime('started_at')->nullable()->after('status');
            $table->dateTime('completed_at')->nullable()->after('started_at');
        });

        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->string('category', 20)->default('delegate')->after('name');
            $table->string('hotel', 160)->nullable()->after('drop_point');
            // Kept apart on purpose: one is for the driver, one for the protocol team.
            $table->string('luggage_note', 200)->nullable()->after('notes');
            $table->string('protocol_note', 300)->nullable()->after('luggage_note');
            $table->dateTime('no_show_at')->nullable()->after('protocol_note');
        });

        // Match the free-text provider against the supplier book. The string stays
        // put as a fallback — a fuzzy match is a suggestion, not a fact.
        foreach (DB::table('suppliers')->get(['id', 'name']) as $supplier) {
            DB::table('event_transport')
                ->whereNull('supplier_id')
                ->whereRaw('LOWER(TRIM(provider)) = ?', [mb_strtolower(trim($supplier->name))])
                ->update(['supplier_id' => $supplier->id]);
        }
    }

    public function down(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->dropColumn(['category', 'hotel', 'luggage_note', 'protocol_note', 'no_show_at']);
        });

        Schema::table('event_transport', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['is_vip', 'delayed_to', 'issue_note', 'started_at', 'completed_at']);
        });

        Schema::dropIfExists('transport_vehicles');
        Schema::dropIfExists('transport_drivers');
    }
};
