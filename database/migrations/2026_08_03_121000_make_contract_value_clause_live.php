<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A contract's project-value clause was frozen at generation time — the exact
 * figure typed into the sentence the moment the document was created, which
 * for a document created before anything had been priced meant it said
 * "0.00" forever, no matter what got entered afterwards in Value & Payments.
 *
 * The clause now quotes {{value}}, a token resolved live at render time (see
 * ContractClauses::resolveValue and blocks.blade.php) — so this migration
 * retrofits every document already generated: it swaps the frozen sentence for
 * the token wherever the sentence still reads as the untouched system text
 * (matched on its title and its opening words), leaving everything else in
 * the document — every other clause, every hand edit — exactly as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('event_contracts')->where('type', 'client')->get() as $row) {
            $data = json_decode($row->data ?? '{}', true) ?: [];
            $blocks = $data['blocks'] ?? null;

            if (! is_array($blocks) || $blocks === []) {
                continue;
            }

            $changed = false;

            foreach ($blocks as $i => $block) {
                $title = $block['title_en'] ?? '';
                if (! in_array($title, ['Estimated Project Value', 'Contract Value'], true)) {
                    continue;
                }

                $isFixed = $title === 'Contract Value';

                if (isset($block['en'][0]) && str_contains($block['en'][0], 'value of the Project is')) {
                    $blocks[$i]['en'][0] = $isFixed
                        ? 'The Parties agree that the total value of the Project is {{value}}.'
                        : 'The Parties agree that the current estimated value of the Project is {{value}}.';
                    $changed = true;
                }

                if (isset($block['ar'][0]) && str_contains($block['ar'][0], 'للمشروع هي')) {
                    $blocks[$i]['ar'][0] = $isFixed
                        ? 'يتفق الطرفان على أن القيمة الإجمالية للمشروع هي {{value}}.'
                        : 'يتفق الطرفان على أن القيمة التقديرية الحالية للمشروع هي {{value}}.';
                    $changed = true;
                }
            }

            if ($changed) {
                $data['blocks'] = $blocks;
                DB::table('event_contracts')->where('id', $row->id)->update(['data' => json_encode($data)]);
            }
        }
    }

    public function down(): void
    {
        // The frozen number the sentence used to carry is gone — there is
        // nothing to restore it to, only the live figure this replaced it with.
    }
};
