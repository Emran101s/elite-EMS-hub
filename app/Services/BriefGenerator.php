<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventApproval;
use App\Models\EventBrief;
use App\Models\EventBudgetCategory;
use App\Models\EventPlanItem;
use App\Models\EventRisk;
use App\Models\EventSponsorPackage;
use Illuminate\Support\Facades\DB;

/**
 * Turns an APPROVED Event Brief into the working ERP records:
 * plan phases + tasks, budget categories, risk register, sponsorship
 * packages and client approval gates.
 *
 * Guarantees:
 *  - Idempotent  — re-running matches on name/title and creates nothing twice.
 *  - Non-destructive — never deletes or overwrites what the team added by hand.
 */
class BriefGenerator
{
    /** Which plan phase each brief section feeds. */
    private const SECTION_PHASE = [
        'components' => 'Planning & Design',
        'venue' => 'Planning & Design',
        'branding' => 'Planning & Design',
        'operational' => 'Pre-Event Readiness',
    ];

    public function generate(EventBrief $brief): array
    {
        $event = $brief->event;
        $data = $brief->data;

        return DB::transaction(function () use ($event, $brief, $data) {
            $summary = [
                'tasks' => $this->tasks($event, $data),
                'milestones' => $this->milestones($event, $data),
                'budget' => $this->budget($event, $data),
                'risks' => $this->risks($event, $data),
                'sponsors' => $this->sponsors($event, $data),
                'approvals' => $this->approvals($event, $data),
            ];

            $brief->forceFill(['generated_at' => now()])->save();

            return $summary;
        });
    }

    // ── Plan: phases + tasks ─────────────────────────────────────────────
    private function tasks(Event $event, array $data): int
    {
        $event->ensurePlanCategories();
        $phases = $event->planCategories()->get()->keyBy('name');
        $made = 0;

        foreach (self::SECTION_PHASE as $section => $phaseName) {
            $phase = $phases[$phaseName] ?? $phases->first();
            if (! $phase) {
                continue;
            }

            foreach ((array) ($data[$section] ?? []) as $row) {
                // twocol rows are ['area','notes']; bullets are plain strings.
                $title = is_array($row) ? trim((string) ($row['area'] ?? '')) : trim((string) $row);
                $notes = is_array($row) ? trim((string) ($row['notes'] ?? '')) : null;

                if ($title === '') {
                    continue;
                }

                $made += $this->planItem($event, $phase->id, $title, $notes) ? 1 : 0;
            }
        }

        return $made;
    }

    private function milestones(Event $event, array $data): int
    {
        $phases = $event->planCategories()->get()->keyBy('name');
        $made = 0;

        foreach ((array) ($data['milestones'] ?? []) as $row) {
            $title = trim((string) ($row['area'] ?? ''));
            if ($title === '') {
                continue;
            }

            $phase = $phases[$this->milestonePhase($title)] ?? $phases->first();
            if (! $phase) {
                continue;
            }

            $made += $this->planItem($event, $phase->id, $title, trim((string) ($row['notes'] ?? '')), 'high') ? 1 : 0;
        }

        return $made;
    }

    /** Create a top-level plan item unless one with that title already exists on the event. */
    private function planItem(Event $event, int $categoryId, string $title, ?string $notes, string $priority = 'medium'): bool
    {
        if ($event->planItems()->whereNull('parent_id')->where('title', $title)->exists()) {
            return false;
        }

        $event->planItems()->create([
            'category_id' => $categoryId,
            'title' => $title,
            'notes' => $notes ?: null,
            'status' => 'todo',
            'priority' => $priority,
            'sort_order' => (int) $event->planItems()->where('category_id', $categoryId)->max('sort_order') + 1,
        ]);

        return true;
    }

    private function milestonePhase(string $title): string
    {
        $t = strtolower($title);

        return match (true) {
            str_contains($t, 'brief') || str_contains($t, 'concept') => 'Initiation & Strategy',
            str_contains($t, 'venue') || str_contains($t, 'site') || str_contains($t, 'floorplan')
                || str_contains($t, 'curriculum') || str_contains($t, 'design') => 'Planning & Design',
            str_contains($t, 'registration') || str_contains($t, 'sponsorship') || str_contains($t, 'ticketing')
                || str_contains($t, 'invitation') || str_contains($t, 'nomination') || str_contains($t, 'sales') => 'Marketing & Registration',
            str_contains($t, 'rehearsal') || str_contains($t, 'readiness') || str_contains($t, 'build')
                || str_contains($t, 'move-in') || str_contains($t, 'permits') || str_contains($t, 'final') => 'Pre-Event Readiness',
            str_contains($t, 'event day') || str_contains($t, 'event dates') || str_contains($t, 'show day')
                || str_contains($t, 'gala night') || str_contains($t, 'delivery') => 'Event Execution',
            str_contains($t, 'move-out') || str_contains($t, 'de-rig') => 'Event Close-Out',
            str_contains($t, 'report') || str_contains($t, 'evaluation') || str_contains($t, 'certificate') => 'Post-Event',
            default => 'Initiation & Strategy',
        };
    }

    // ── Budget ───────────────────────────────────────────────────────────
    private function budget(Event $event, array $data): int
    {
        $made = 0;
        $pos = (int) EventBudgetCategory::where('event_id', $event->id)->max('position');

        foreach ((array) ($data['budget'] ?? []) as $row) {
            $name = trim((string) ($row['area'] ?? ''));
            if ($name === '' || EventBudgetCategory::where('event_id', $event->id)->where('name', $name)->exists()) {
                continue;
            }

            EventBudgetCategory::create(['event_id' => $event->id, 'name' => $name, 'position' => ++$pos]);
            $made++;
        }

        return $made;
    }

    // ── Risks ────────────────────────────────────────────────────────────
    private function risks(Event $event, array $data): int
    {
        $made = 0;

        foreach ((array) ($data['risks'] ?? []) as $row) {
            $title = trim((string) ($row['area'] ?? ''));
            if ($title === '' || EventRisk::where('event_id', $event->id)->where('title', $title)->exists()) {
                continue;
            }

            EventRisk::create([
                'event_id' => $event->id,
                'title' => $title,
                'category' => $this->riskCategory($title),
                'probability' => 3,
                'impact' => 4,
                'mitigation' => trim((string) ($row['notes'] ?? '')) ?: null,
                'status' => 'open',
            ]);
            $made++;
        }

        return $made;
    }

    private function riskCategory(string $title): string
    {
        $t = strtolower($title);

        return match (true) {
            str_contains($t, 'attendance') || str_contains($t, 'registration') || str_contains($t, 'turnout')
                || str_contains($t, 'enrol') || str_contains($t, 'no-show') || str_contains($t, 'booth sales') => 'attendance',
            str_contains($t, 'speaker') || str_contains($t, 'keynote') || str_contains($t, 'trainer')
                || str_contains($t, 'talent') || str_contains($t, 'facilitator') => 'speaker',
            str_contains($t, 'technical') || str_contains($t, 'power') || str_contains($t, 'generator')
                || str_contains($t, 'livestream') || str_contains($t, 'internet') || str_contains($t, 'av') => 'technical',
            str_contains($t, 'sponsor') || str_contains($t, 'budget') || str_contains($t, 'cost') => 'budget',
            str_contains($t, 'weather') => 'weather',
            str_contains($t, 'venue') || str_contains($t, 'floorplan') || str_contains($t, 'permit')
                || str_contains($t, 'fire-code') || str_contains($t, 'site') => 'venue',
            str_contains($t, 'supplier') || str_contains($t, 'vendor') || str_contains($t, 'move-in')
                || str_contains($t, 'material') || str_contains($t, 'print') => 'supplier',
            str_contains($t, 'production') || str_contains($t, 'stage') || str_contains($t, 'entertainment') => 'production',
            str_contains($t, 'approval') || str_contains($t, 'client') => 'client_approval',
            default => 'logistics',
        };
    }

    // ── Sponsorship packages (from the Stakeholders → Sponsors row) ───────
    private function sponsors(Event $event, array $data): int
    {
        $tiers = [];

        foreach ((array) ($data['stakeholders'] ?? []) as $row) {
            if (strtolower(trim((string) ($row['area'] ?? ''))) === 'sponsors') {
                $tiers = $this->splitList((string) ($row['notes'] ?? ''));
                break;
            }
        }

        $made = 0;
        $pos = (int) EventSponsorPackage::where('event_id', $event->id)->max('position');

        foreach ($tiers as $tier) {
            if (EventSponsorPackage::where('event_id', $event->id)->where('name', $tier)->exists()) {
                continue;
            }

            EventSponsorPackage::create([
                'event_id' => $event->id,
                'name' => $tier,
                'price_cents' => 0,
                'slots' => null,
                'benefits' => [],
                'position' => ++$pos,
            ]);
            $made++;
        }

        return $made;
    }

    // ── Client approval gates (from Governance → Approval Process) ────────
    private function approvals(Event $event, array $data): int
    {
        $gates = [];

        foreach ((array) ($data['governance'] ?? []) as $row) {
            if (str_contains(strtolower((string) ($row['area'] ?? '')), 'approval')) {
                $notes = (string) ($row['notes'] ?? '');
                // "Client approval required for scope, budget, venue, ..." → take the list after "for".
                if (preg_match('/\bfor\b(.*)$/i', $notes, $m)) {
                    $notes = $m[1];
                }
                $gates = $this->splitList($notes);
                break;
            }
        }

        $made = 0;
        $requester = $event->project_manager_id;

        foreach ($gates as $gate) {
            $title = 'Client approval — '.ucfirst($gate);
            if (EventApproval::where('event_id', $event->id)->where('title', $title)->exists()) {
                continue;
            }

            EventApproval::create([
                'event_id' => $event->id,
                'title' => $title,
                'type' => $this->approvalType($gate),
                'status' => 'pending',
                'requested_by' => $requester,
                'notes' => 'Generated from the approved Event Brief.',
            ]);
            $made++;
        }

        return $made;
    }

    private function approvalType(string $gate): string
    {
        $g = strtolower($gate);

        return match (true) {
            str_contains($g, 'budget') => 'budget',
            str_contains($g, 'venue') => 'venue',
            str_contains($g, 'branding') || str_contains($g, 'design') => 'design',
            str_contains($g, 'agenda') || str_contains($g, 'programme') || str_contains($g, 'program') => 'agenda',
            str_contains($g, 'report') => 'report',
            str_contains($g, 'supplier') => 'supplier',
            default => 'client',
        };
    }

    /** "a, b, and c." → ['a','b','c'] */
    private function splitList(string $text): array
    {
        $parts = preg_split('/,|\band\b/i', $text) ?: [];

        return collect($parts)
            ->map(fn ($p) => trim(rtrim(trim($p), '.')))
            ->filter(fn ($p) => $p !== '' && mb_strlen($p) < 60)
            ->unique()
            ->values()
            ->all();
    }
}
