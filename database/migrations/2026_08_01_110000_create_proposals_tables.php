<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proposals — the priced offer that goes out before there is anything to sign.
 *
 * The pipeline already has a "proposal" stage, but nothing behind it: the stage
 * recorded that an offer had been made and the offer itself lived in somebody's
 * outbox. So the number a deal was worth was typed twice, once into the
 * document and once into the deal, and they drifted.
 *
 * A proposal carries its own priced lines and belongs to the deal it is trying
 * to win. Accepting one wins that deal, which is what creates the event — so
 * the figure the client agreed to is the figure the event is budgeted at,
 * without anybody retyping it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();

            // A proposal is usually for a deal, but not always: a repeat client
            // can ask for a price on an event that already exists.
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number')->unique();
            $table->string('title');

            // Only what the office decided is stored: draft, sent, accepted,
            // declined. Expiry is derived from valid_until, the same way an
            // invoice derives overdue — a date passing is not an act.
            $table->string('status')->default('draft');

            $table->string('currency', 3)->default('JOD');
            $table->date('issued_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->float('tax_pct')->default(0);

            $table->text('summary')->nullable();
            $table->text('terms')->nullable();
            $table->date('decided_on')->nullable();
            $table->text('decline_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'valid_until']);
            $table->index('deal_id');
        });

        Schema::create('proposal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();

            $table->string('description');
            $table->text('detail')->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->bigInteger('unit_cents')->default(0);

            // An optional extra is quoted but not counted: it is in the offer so
            // the client can say yes to it, and out of the total so the headline
            // price is the one they are being asked to agree to.
            $table->boolean('optional')->default(false);

            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['proposal_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_lines');
        Schema::dropIfExists('proposals');
    }
};
