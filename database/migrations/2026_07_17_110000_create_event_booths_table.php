<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booths become sellable inventory. A booth exists on the floor with an asking
 * price before anyone buys it; linking an exhibitor is the sale. Status is
 * derived from that link (available / reserved / sold), never stored, so the
 * floor plan and the money can't disagree.
 *
 * Backfills one booth per exhibitor that is already placed on a hall floor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_booths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained('event_exhibition_halls')->cascadeOnDelete();
            $table->foreignId('exhibitor_id')->nullable()->unique()
                ->constrained('event_exhibitors')->nullOnDelete();
            $table->string('number', 16);
            $table->unsignedInteger('price_cents')->default(0);
            $table->float('x');
            $table->float('y');
            $table->float('w_m');
            $table->float('h_m');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'number']);
        });

        $this->backfill();
    }

    /** Every placed exhibitor already is a de-facto booth — make it a real one. */
    private function backfill(): void
    {
        $placed = DB::table('event_exhibitors')
            ->whereNotNull('hall_id')->whereNotNull('booth_x')
            ->orderBy('id')->get();

        $used = [];
        foreach ($placed as $i => $ex) {
            $number = trim((string) ($ex->booth_number ?? ''));
            if ($number === '' || isset($used[$ex->event_id][$number])) {
                $number = 'B'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            }
            $used[$ex->event_id][$number] = true;

            DB::table('event_booths')->insert([
                'event_id' => $ex->event_id,
                'hall_id' => $ex->hall_id,
                'exhibitor_id' => $ex->id,
                'number' => $number,
                'price_cents' => $ex->fee_cents ?? 0,
                'x' => $ex->booth_x,
                'y' => $ex->booth_y,
                'w_m' => $ex->booth_w_m ?: 3,
                'h_m' => $ex->booth_h_m ?: 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_booths');
    }
};
