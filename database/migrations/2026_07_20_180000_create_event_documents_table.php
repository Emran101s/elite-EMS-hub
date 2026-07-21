<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A document belongs to an event and, optionally, to one of its modules — so a
 * signed hotel contract dropped on Accommodation is visible there rather than
 * buried in one undifferentiated file pile.
 *
 * `module` is a HUB_MODULES key, or null for event-wide papers. It is a plain
 * string rather than an FK: modules are code constants, not rows, and a
 * document should survive a module being switched off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40)->nullable();
            $table->string('name', 160);              // what a person calls it
            $table->string('original_name', 200);     // what the file was called
            $table->string('path', 400);
            $table->string('disk', 30)->default('local');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 400)->nullable();
            $table->timestamps();

            $table->index(['event_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_documents');
    }
};
