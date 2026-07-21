<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two company-wide catalogues behind the transport module:
 *  - vehicle_types          what you move people in  (Sedan max 2, Van max 7, Bus 49…)
 *  - transport_service_types what the movement is    (Pickup & drop-off, Airport → Hotel, Full day…)
 *
 * Both ship with presets. Only the handful you actually use start active; the
 * rest sit in Settings waiting to be switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('transport_service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('event_transport', function (Blueprint $table) {
            $table->foreignId('vehicle_type_id')->nullable()->after('type')
                ->constrained('vehicle_types')->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->after('vehicle_type_id')
                ->constrained('transport_service_types')->nullOnDelete();
            $table->unsignedInteger('vehicles')->default(1)->after('service_type_id');
            $table->string('pickup_from', 160)->nullable()->after('route');
            $table->string('drop_to', 160)->nullable()->after('pickup_from');
        });
    }

    public function down(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->dropForeign(['vehicle_type_id']);
            $table->dropForeign(['service_type_id']);
            $table->dropColumn(['vehicle_type_id', 'service_type_id', 'vehicles', 'pickup_from', 'drop_to']);
        });
        Schema::dropIfExists('transport_service_types');
        Schema::dropIfExists('vehicle_types');
    }
};
