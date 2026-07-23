<?php

use App\Models\EventTransport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every movement gets a number you can say out loud: "put them on car 3".
 * Numbers are handed out per event and never reused — a driver holding a sheet
 * for car 3 must not find a different car there tomorrow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->unsignedSmallInteger('ref_no')->nullable()->after('event_id');
        });

        // Backfill in the order they run, so existing plans read 1, 2, 3 down the page.
        EventTransport::all()
            ->groupBy('event_id')
            ->each(function ($movements) {
                $n = 0;
                foreach ($movements->sortBy(fn (EventTransport $m) => [$m->chronoKey(), $m->id]) as $m) {
                    $m->updateQuietly(['ref_no' => ++$n]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->dropColumn('ref_no');
        });
    }
};
