<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventBrief;
use App\Models\EventBudgetCategory;
use App\Models\EventRisk;
use App\Models\EventSponsorPackage;
use Illuminate\Support\Facades\DB;

/**
 * Turns an APPROVED Event Brief into the working ERP records:
 * budget categories, risk register, and sponsorship packages.
 *
 * Guarantees:
 *  - Idempotent  — re-running matches on name/title and creates nothing twice.
 *  - Non-destructive — never deletes or overwrites what the team added by hand.
 */
class BriefGenerator
{
    public function generate(EventBrief $brief): array
    {
        $event = $brief->event;
        $data = $brief->data;

        return DB::transaction(function () use ($event, $brief, $data) {
            $summary = [
                'budget' => $this->budget($event, $data),
                'risks' => $this->risks($event, $data),
                'sponsors' => $this->sponsors($event, $data),
            ];

            $brief->forceFill(['generated_at' => now()])->save();

            return $summary;
        });
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

    // ── Sponsorship packages (from Sponsors & Partners → Sponsorship Tiers) ──
    private function sponsors(Event $event, array $data): int
    {
        $tiers = [];

        foreach ((array) ($data['sponsors'] ?? []) as $row) {
            if (str_contains(strtolower((string) ($row['area'] ?? '')), 'tier')) {
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
