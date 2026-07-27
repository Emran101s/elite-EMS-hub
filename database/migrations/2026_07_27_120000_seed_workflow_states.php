<?php

use App\Support\Workflow;
use Illuminate\Database\Migrations\Migration;

/**
 * The workflow states, so their wording and colour can be yours.
 *
 * The keys stay exactly where they are — `won`, `done` and `live` are things
 * the code reasons about, and the sets are closed. What this makes editable is
 * the wording and the colour, which is the part that was never a process
 * decision in the first place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Workflow::seed();
    }

    public function down(): void
    {
        // Nothing to undo: removing wording someone chose would lose their work.
    }
};
