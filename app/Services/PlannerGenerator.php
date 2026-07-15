<?php

namespace App\Services;

use App\Models\Event;

class PlannerGenerator
{
    /** Wizard questions: key => [label, type, default]. type: number|bool. */
    public const QUESTIONS = [
        'attendees' => ['Expected attendees', 'number', 500],
        'days' => ['Number of days', 'number', 2],
        'venues' => ['Number of venues', 'number', 1],
        'tracks' => ['Number of parallel tracks', 'number', 1],
        'has_speakers' => ['Speakers & moderators', 'bool', true],
        'has_exhibition' => ['Exhibition / booths', 'bool', false],
        'has_sponsors' => ['Sponsors & partners', 'bool', true],
        'has_vip' => ['VIP guests & protocol', 'bool', false],
        'has_accommodation' => ['Accommodation & travel', 'bool', false],
        'has_transportation' => ['Transportation', 'bool', false],
        'has_gala' => ['Gala / VIP dinner', 'bool', false],
        'has_workshops' => ['Workshops', 'bool', false],
        'has_livestream' => ['Livestreaming / hybrid', 'bool', false],
        'has_interpretation' => ['Interpretation / translation', 'bool', false],
        'has_app' => ['Event app', 'bool', false],
    ];

    /** Expand the wizard config into the full set of condition flags. */
    public function deriveFlags(array $config): array
    {
        $flags = [];
        foreach (PlannerLibrary::CONDITIONS as $c) {
            $flags[$c] = (bool) ($config[$c] ?? false);
        }
        $flags['multi_venue'] = (int) ($config['venues'] ?? 1) > 1;
        $flags['multi_track'] = (int) ($config['tracks'] ?? 1) > 1;
        $flags['large'] = (int) ($config['attendees'] ?? 0) >= 1000;

        return $flags;
    }

    /**
     * Generate (or refresh) the event plan from the library. Idempotent:
     * new applicable tasks are added, library-driven fields on existing tasks
     * are refreshed, and user progress (status, owner, notes) is preserved.
     */
    public function generate(Event $event, array $config): int
    {
        $event->update(['planner_config' => $config]);

        $tasks = PlannerLibrary::applicableTasks($this->deriveFlags($config));
        $existing = $event->planItems()->get()->keyBy('template_key');
        $added = 0;

        foreach ($tasks as $order => $t) {
            $due = PlannerLibrary::resolveDate($t['deadline'], $event->starts_at);

            $libraryFields = [
                'workstream' => $t['ws'],
                'phase' => $t['phase'],
                'title' => $t['name'],
                'description' => $t['desc'],
                'priority' => $t['priority'],
                'owner_role' => $t['owner'],
                'deadline_code' => $t['deadline'],
                'due_on' => $due,
                'approval_required' => $t['approval'],
                'budget_impact' => $t['budget'],
                'risk_level' => $t['risk'],
                'sort_order' => $order,
            ];

            if ($item = $existing->get($t['key'])) {
                $item->update($libraryFields);   // refresh library fields, keep status/owner/notes
            } else {
                $event->planItems()->create($libraryFields + [
                    'template_key' => $t['key'],
                    'status' => 'todo',
                    'approval_status' => $t['approval'] ? 'pending' : 'none',
                ]);
                $added++;
            }
        }

        return $added;
    }
}
