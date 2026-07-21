<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contract promises 30/30/20/20 — this table knows whether the client
 * actually paid. One row per installment, generated from the contract's
 * payment schedule, each with a concrete due date and money received.
 * Cash flow is the one number an agency can't afford to guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_contract_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('event_contracts')->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->string('label');                         // "On signing of this Agreement"
            $table->float('pct');
            $table->unsignedInteger('amount_cents');
            $table->date('due_on')->nullable();
            $table->unsignedInteger('paid_cents')->default(0);
            $table->date('paid_at')->nullable();
            $table->string('note')->nullable();              // receipt ref, transfer id …
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_contract_payments');
    }
};
