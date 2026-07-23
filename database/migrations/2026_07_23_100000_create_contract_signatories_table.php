<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per person who must sign a contract. The signature fields stay null
 * until they sign; when they do, we record WHO, WHEN, from WHERE, and the HASH
 * of exactly the document they signed — the defensible audit trail that lets a
 * simple typed / wet signature stand up later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('event_contracts')->cascadeOnDelete();
            $table->string('role', 20)->default('organiser');   // organiser/client/vendor/speaker/sponsor/witness
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('order')->default(0);

            // Filled at the moment of signing — the audit trail.
            $table->dateTime('signed_at')->nullable();
            $table->string('signature_data')->nullable();   // typed name (Phase 3) or drawn SVG later
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_hash', 64)->nullable();   // sha256 of the document content at signing

            $table->timestamps();
            $table->index(['contract_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signatories');
    }
};
