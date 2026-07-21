<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subfolders inside a module's document drawer — "Signed contracts", "Quotes",
 * "Floor plans". A folder belongs to one module (or to the event-wide drawer
 * when module is null) and may nest inside another folder.
 *
 * Module folders themselves are not rows: they are derived from HUB_MODULES, so
 * every event has the same top-level drawers without seeding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40)->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('event_document_folders')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'module', 'parent_id']);
        });

        Schema::table('event_documents', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('module')
                ->constrained('event_document_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_documents', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });
        Schema::dropIfExists('event_document_folders');
    }
};
