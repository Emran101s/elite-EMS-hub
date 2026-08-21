<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venue's own files — contracts, floor plans, technical specs, insurance,
 * permits. Deliberately flat (no folder hierarchy like event_documents/
 * event_document_folders): a venue's document volume is a handful of files,
 * not a per-module explosion, so `category` alone is enough to organize it.
 *
 * status is contract-only (draft|sent|signed|expired) — every other category
 * leaves it null. Narrower than EventContract::STATUSES on purpose: there is
 * no ContractSignatory tracking here, so "partially signed" has nothing to
 * derive from, and "expired" (not "void") matches the real failure mode for
 * a venue agreement going stale rather than being countersigned then voided.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30)->default('other'); // contract|floor_plan|tech_spec|insurance|permit|other
            $table->string('status', 20)->nullable();          // draft|sent|signed|expired — contract only
            $table->string('name', 160);
            $table->string('original_name', 200);
            $table->string('path', 400);
            $table->string('disk', 30)->default('local');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 400)->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'category']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_documents');
    }
};
