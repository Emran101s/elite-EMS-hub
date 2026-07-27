<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CRM: the half of the business that happens before an event exists.
 *
 * Three tables. Contacts, because a client is an organisation with people in
 * it and `clients.contact_name` was one string. Deals, the work you are trying
 * to win, which becomes an Event the moment you win it. Activities, the record
 * of who spoke to whom — the thing that makes a pipeline trustworthy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();          // "Head of Events"
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'is_primary']);
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('stage')->default('enquiry');   // App\Models\Deal::STAGES
            $table->string('type')->nullable();            // the event type it would become
            $table->unsignedBigInteger('value_cents')->default(0);
            $table->string('currency', 3)->default('JOD');
            $table->unsignedTinyInteger('probability')->default(0);

            $table->date('expected_close_on')->nullable();  // when they decide
            $table->date('expected_event_on')->nullable();  // when it would run
            $table->string('source')->nullable();           // referral, tender, inbound…
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);

            // Set the moment the deal is won: the event it became. Null until then,
            // and nulled rather than cascaded if that event is ever deleted, so the
            // deal survives as history.
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();

            $table->timestamps();

            $table->index(['stage', 'position']);
            $table->index('expected_close_on');
        });

        Schema::create('deal_activities', function (Blueprint $table) {
            $table->id();
            // Logged against a deal, or against the client when there is no
            // live deal — a relationship does not pause between opportunities.
            $table->foreignId('deal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type')->default('note');       // call · meeting · email · note
            $table->string('subject');
            $table->text('body')->nullable();
            $table->timestamp('happened_at');
            $table->date('follow_up_on')->nullable();
            $table->boolean('follow_up_done')->default(false);
            $table->timestamps();

            $table->index(['client_id', 'happened_at']);
            $table->index(['follow_up_on', 'follow_up_done']);
        });

        // The single contact each client already had becomes its primary contact,
        // so no one loses a phone number to this refactor.
        foreach (DB::table('clients')->get() as $client) {
            if (! filled($client->contact_name) && ! filled($client->email) && ! filled($client->phone)) {
                continue;
            }

            DB::table('contacts')->insert([
                'client_id' => $client->id,
                'name' => $client->contact_name ?: $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_activities');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('contacts');
    }
};
