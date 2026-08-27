<?php

namespace App\Support;

use App\Models\TaxonomyTerm;
use Illuminate\Support\Collection;

/**
 * The platform's workflow states, made re-labellable and re-colourable.
 *
 * These are the sibling of Taxonomy, and the difference is the whole point.
 * A type is vocabulary: you add "Board Retreat" because you run one. A state
 * is a position in a process the code reasons about — `won` closes a deal and
 * creates an event, `done` takes a task out of the count, `live` decides what
 * the Command Center shows. You cannot add a seventh deal stage and expect the
 * pipeline to know what it means, and deleting `done` would break counting.
 *
 * So the keys are fixed and the set is closed. What is genuinely yours is the
 * wording and the colour: if your company says "In Production" where the code
 * says `doing`, that is a naming difference, not a process difference, and you
 * should not need a developer for it.
 *
 * Storage is the same taxonomy_terms table — a state is a term whose key
 * happens to be locked. Taxonomy::LISTS does not name these sets, so the
 * Types & Lists screen never shows them and seed()/adopt() never touch them.
 */
class Workflow
{
    /**
     * Every state set.
     *
     *   label   what the set is called on screen
     *   note    what the states drive
     *   states  key => [default label, default hex] — the key is never editable
     *
     * @var array<string,array{label:string,note:string,states:array<string,array{0:string,1:string}>}>
     */
    public const SETS = [
        'event_stage' => [
            'label' => 'Event stages',
            'note' => 'Where an event is in its life. Drives the Operations Hub, the health score and what counts as active.',
            'states' => [
                'draft' => ['Draft', '#94A3B8'],
                'proposal' => ['Proposal', '#3B82F6'],
                'confirmed' => ['Confirmed', '#06B6D4'],
                'planning' => ['Planning', '#8B5CF6'],
                'production' => ['Production', '#D4AF37'],
                'live' => ['Live', '#22C55E'],
                'completed' => ['Completed', '#10B981'],
                'closed' => ['Closed', '#64748B'],
                'cancelled' => ['Cancelled', '#F87171'],
                'on_hold' => ['On Hold', '#F59E0B'],
            ],
        ],
        'deal_stage' => [
            'label' => 'Deal stages',
            'note' => 'The pipeline lanes. Winning a deal creates the event, so the set itself is fixed.',
            'states' => [
                'enquiry' => ['Enquiry', '#94A3B8'],
                'qualified' => ['Qualified', '#3B82F6'],
                'proposal' => ['Proposal', '#D4AF37'],
                'negotiation' => ['Negotiation', '#F97316'],
                'won' => ['Won', '#22C55E'],
                'lost' => ['Lost', '#94A3B8'],
            ],
        ],
        'task_stage' => [
            'label' => 'Task stages',
            'note' => 'The columns on every task board.',
            'states' => [
                'todo' => ['To Do', '#94A3B8'],
                'doing' => ['In Progress', '#D4AF37'],
                'review' => ['Need Approval', '#F97316'],
                'approved' => ['Approved', '#10B981'],
                'done' => ['Done', '#22C55E'],
                'cancelled' => ['Cancelled', '#64748B'],
            ],
        ],
        'task_priority' => [
            'label' => 'Task priorities',
            'note' => 'How urgent a task is.',
            'states' => [
                'low' => ['Low', '#94A3B8'],
                'normal' => ['Normal', '#3B82F6'],
                'high' => ['High', '#F59E0B'],
                'urgent' => ['Urgent', '#EF4444'],
            ],
        ],
        'plan_status' => [
            'label' => 'Plan item statuses',
            'note' => 'Where a deliverable is on the planning board.',
            'states' => [
                'todo' => ['To Do', '#94A3B8'],
                'in_progress' => ['In Progress', '#D4AF37'],
                'needs_approval' => ['Need Approval', '#F97316'],
                'approved' => ['Approved', '#10B981'],
                'done' => ['Done', '#22C55E'],
                'cancelled' => ['Cancelled', '#64748B'],
            ],
        ],
        'session_status' => [
            'label' => 'Session statuses',
            'note' => 'How settled an agenda session is. Confirmed and Final both count as settled.',
            'states' => [
                'draft' => ['Draft', '#94A3B8'],
                'waiting_speaker' => ['Awaiting speaker', '#F59E0B'],
                'needs_review' => ['Needs review', '#F97316'],
                'confirmed' => ['Confirmed', '#10B981'],
                'final' => ['Final', '#22C55E'],
            ],
        ],
        'attendee_status' => [
            'label' => 'Attendee statuses',
            'note' => 'Where someone is between registering and walking in.',
            'states' => [
                'registered' => ['Registered', '#64748B'],
                'confirmed' => ['Confirmed', '#3B82F6'],
                'checked_in' => ['Checked in', '#10B981'],
                'cancelled' => ['Cancelled', '#94A3B8'],
            ],
        ],
        'transport_status' => [
            'label' => 'Movement statuses',
            'note' => 'The state of a transport movement, from planned to delivered.',
            'states' => [
                'planned' => ['Planned', '#64748B'],
                'ordered' => ['Ordered', '#F59E0B'],
                'confirmed' => ['Confirmed', '#22C55E'],
                'in_progress' => ['In progress', '#3B82F6'],
                'completed' => ['Completed', '#94A3B8'],
                'issue' => ['Issue', '#EF4444'],
                'cancelled' => ['Cancelled', '#94A3B8'],
            ],
        ],
        'contract_status' => [
            'label' => 'Document statuses',
            'note' => 'Where a contract or agreement is on its way to being signed.',
            'states' => [
                'draft' => ['Draft', '#94A3B8'],
                'sent' => ['Sent', '#3B82F6'],
                'partially_signed' => ['Partially signed', '#F59E0B'],
                'signed' => ['Signed', '#22C55E'],
                'void' => ['Void', '#64748B'],
            ],
        ],
        'payment_status' => [
            'label' => 'Payment statuses',
            'note' => 'How far a budget line has been settled.',
            'states' => [
                'pending' => ['Pending', '#F59E0B'],
                'partial' => ['Partly paid', '#3B82F6'],
                'paid' => ['Paid', '#22C55E'],
            ],
        ],
    ];

    private static array $memo = [];

    public static function meta(string $set, string $field): mixed
    {
        return self::SETS[$set][$field] ?? match ($field) {
            'label' => str($set)->headline()->toString(),
            'note' => '',
            default => [],
        };
    }

    /** The stored rows for a set, in display order. */
    public static function terms(string $set): Collection
    {
        return self::$memo[$set] ??= TaxonomyTerm::query()->in(self::taxonomy($set))->get();
    }

    /** taxonomy_terms holds these under a prefix so the two never collide. */
    public static function taxonomy(string $set): string
    {
        return 'state:'.$set;
    }

    /** Every state with its wording, colour and whether it is still offered. */
    public static function rows(string $set): Collection
    {
        $stored = self::terms($set)->keyBy('key');

        return collect(self::meta($set, 'states'))->map(fn (array $default, string $key) => [
            'key' => $key,
            'label' => $stored[$key]->label ?? $default[0],
            'color' => $stored[$key]->color ?: $default[1],
            'active' => $stored[$key]->is_active ?? true,
            'id' => $stored[$key]->id ?? null,
            'position' => $stored[$key]->position ?? 0,
            'default_label' => $default[0],
            'default_color' => $default[1],
        ])->sortBy('position')->values();
    }

    public static function label(string $set, ?string $key): string
    {
        if (! $key) {
            return '—';
        }

        return self::terms($set)->firstWhere('key', $key)?->label
            ?? self::meta($set, 'states')[$key][0]
            ?? str($key)->replace('_', ' ')->title()->toString();
    }

    public static function color(string $set, ?string $key): string
    {
        if (! $key) {
            return '#64748B';
        }

        $stored = self::terms($set)->firstWhere('key', $key)?->color;

        return $stored ?: (self::meta($set, 'states')[$key][1] ?? '#64748B');
    }

    /** Fill any set that has no rows yet from its defaults. Idempotent. */
    public static function seed(): int
    {
        $made = 0;

        foreach (self::SETS as $set => $meta) {
            $position = 0;

            foreach ($meta['states'] as $key => [$label, $hex]) {
                $term = TaxonomyTerm::firstOrCreate(
                    ['taxonomy' => self::taxonomy($set), 'key' => $key],
                    ['label' => $label, 'color' => $hex, 'position' => $position, 'is_system' => true],
                );

                $made += $term->wasRecentlyCreated ? 1 : 0;
                $position++;
            }
        }

        self::$memo = [];

        return $made;
    }

    public static function forget(): void
    {
        self::$memo = [];
    }
}
