<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The answer to "who did this?". With approvals, contracts and money in one
 * system, every decision needs an author and a timestamp. Append-only: rows
 * are written by model observers and never updated or deleted from the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('action', 16);                    // created | updated | deleted
            $table->string('auditable_type', 64);
            $table->unsignedBigInteger('auditable_id');
            $table->string('label');                         // human-readable subject
            $table->json('changes')->nullable();             // field => [from, to]
            $table->timestamp('created_at');

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
