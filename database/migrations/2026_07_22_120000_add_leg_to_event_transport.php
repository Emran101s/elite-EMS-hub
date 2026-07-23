<?php

use App\Models\EventTransport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A movement's real category is the one the flight list already uses: is this
 * people arriving, people leaving, or something else entirely (venue shuttles,
 * city runs, a day at disposal). The service type stays as detail underneath.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->string('leg', 12)->default('other')->after('type');
        });

        // Backfill from what each movement already tells us — the people on it
        // first, since a manifest of arrivals is not open to interpretation.
        EventTransport::with('manifest')->chunkById(200, function ($movements) {
            foreach ($movements as $m) {
                $m->updateQuietly(['leg' => EventTransport::inferLeg(
                    $m->pickup_from ?? '',
                    $m->drop_to ?? '',
                    $m->route ?? '',
                    $m->manifest->pluck('direction')->filter()->all(),
                )]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->dropColumn('leg');
        });
    }
};
