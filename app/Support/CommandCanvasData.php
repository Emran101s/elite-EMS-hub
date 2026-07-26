<?php

namespace App\Support;

/**
 * Static data for the Elite Command Canvas.
 *
 * Deliberately a single seam: every method here returns the exact shape the
 * components consume, so swapping a method body for a real query later changes
 * nothing in the views. Nothing in this class touches the database yet.
 */
class CommandCanvasData
{
    /** The dark KPI strip across the top. */
    public static function pulse(): array
    {
        return [
            ['label' => 'Active Events', 'value' => '24', 'delta' => '8%', 'dir' => 'up', 'icon' => 'events'],
            ['label' => 'Participants', 'value' => '8,560', 'delta' => '12.5%', 'dir' => 'up', 'icon' => 'people'],
            ['label' => 'Total Revenue', 'value' => 'JD 1.48M', 'delta' => '18.6%', 'dir' => 'up', 'icon' => 'money'],
            ['label' => 'Open Tasks', 'value' => '380', 'delta' => '2.3%', 'dir' => 'down', 'icon' => 'tasks'],
            ['label' => 'Open Risks', 'value' => '17', 'delta' => '16%', 'dir' => 'up', 'icon' => 'risk', 'tone' => 'risk'],
            ['label' => 'Pending Approvals', 'value' => '47', 'badge' => '6 urgent', 'icon' => 'approve'],
        ];
    }

    public static function health(): array
    {
        return ['value' => 72, 'label' => 'Good'];
    }

    /** The floating icon dock on the far left. */
    public static function dock(): array
    {
        return [
            ['key' => 'home', 'label' => 'Home', 'icon' => 'home'],
            ['key' => 'events', 'label' => 'Events', 'icon' => 'events'],
            ['key' => 'people', 'label' => 'People', 'icon' => 'people'],
            ['key' => 'plan', 'label' => 'Plan', 'icon' => 'plan'],
            ['key' => 'money', 'label' => 'Money', 'icon' => 'money'],
            ['key' => 'live', 'label' => 'Live', 'icon' => 'live'],
            ['key' => 'intelligence', 'label' => 'Intelligence', 'icon' => 'intel'],
            ['key' => 'vault', 'label' => 'Vault', 'icon' => 'vault'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings'],
        ];
    }

    /** The one event at the centre of the canvas. */
    public static function primaryEvent(): array
    {
        return [
            'name' => 'World Public Summit 2026',
            'dates' => 'Nov 8 – Nov 12, 2026',
            'venue' => 'St. Regis Amman, Jordan',
            'health' => 96,
            'participants' => '650',
            'risks' => 3,
            'nextAction' => 'Review VIP Transport',
        ];
    }

    /**
     * The events orbiting it. `x` / `y` are percentages of the canvas box, so
     * the layout holds at any width without hand-placing pixels.
     */
    public static function events(): array
    {
        return [
            [
                'name' => 'ICFT Conference 2026', 'status' => 'planning', 'statusLabel' => 'Planning',
                'dates' => 'Sep 5 – Sep 8, 2026', 'location' => 'Dead Sea, Jordan',
                'health' => 74, 'participants' => '420', 'budget' => 'JD 210K',
                'foot' => '2 Risks', 'footTone' => 'risk',
                'x' => 11, 'y' => 5, 'w' => 30,
            ],
            [
                'name' => 'Tech Expo 2026', 'status' => 'progress', 'statusLabel' => 'In Progress',
                'dates' => 'Oct 28 – Oct 31, 2026', 'location' => 'Dubai, UAE',
                'health' => 82, 'participants' => '1,250', 'budget' => 'JD 560K',
                'foot' => '1 Risk', 'footTone' => 'risk',
                'x' => 64, 'y' => 5, 'w' => 30,
            ],
            [
                'name' => 'EY Bootcamp 2026', 'status' => 'planning', 'statusLabel' => 'Planning',
                'dates' => 'Aug 20, 2026', 'location' => 'Amman, Jordan',
                'health' => 89, 'participants' => '80', 'budget' => 'JD 75K',
                'foot' => 'On Track', 'footTone' => 'ok',
                'x' => 1, 'y' => 45, 'w' => 30,
            ],
            [
                'name' => 'NDI Summit 2026', 'status' => 'risk', 'statusLabel' => 'At Risk',
                'dates' => 'Dec 10 – Dec 12, 2026', 'location' => 'Dubai, UAE',
                'health' => 48, 'participants' => '320', 'budget' => 'JD 180K',
                'foot' => '7 Risks', 'footTone' => 'risk',
                'x' => 69, 'y' => 45, 'w' => 30,
            ],
            [
                'name' => 'Annual Gala 2026', 'status' => 'confirmed', 'statusLabel' => 'Confirmed',
                'dates' => 'Dec 20, 2026', 'location' => 'Four Seasons Amman',
                'health' => 96, 'participants' => '300', 'budget' => 'JD 120K',
                'foot' => 'On Track', 'footTone' => 'ok',
                'x' => 35, 'y' => 78, 'w' => 30,
            ],
        ];
    }

    /** AI Executive Director — the recommended route for today. */
    public static function aiRoute(): array
    {
        return [
            ['title' => 'World Summit: VIP transport approval', 'impact' => 'High impact', 'due' => 'Due today', 'tone' => 'risk'],
            ['title' => 'ICFT Conference: Venue contract', 'impact' => 'Medium impact', 'due' => 'Due in 2 days', 'tone' => 'warn'],
            ['title' => 'EY Bootcamp: Hotel confirmation', 'impact' => 'Medium impact', 'due' => 'Due in 3 days', 'tone' => 'warn'],
            ['title' => 'NDI Summit: Supplier delay risk', 'impact' => 'Low impact', 'due' => 'Due in 4 days', 'tone' => 'info'],
        ];
    }

    /** Live Signals — real-time operational alerts. */
    public static function signals(): array
    {
        return [
            ['impact' => 'High Impact', 'tone' => 'risk', 'title' => 'Venue setup delay', 'context' => 'World Summit · Main Arena', 'time' => '10:25 AM'],
            ['impact' => 'Medium Impact', 'tone' => 'warn', 'title' => 'Speaker arrival pending', 'context' => 'ICFT Conference · 2 Speakers', 'time' => '09:40 AM'],
            ['impact' => 'Low Impact', 'tone' => 'info', 'title' => 'Registration nearing capacity', 'context' => 'Tech Expo · 92% completed', 'time' => '09:15 AM'],
            ['impact' => 'Medium Impact', 'tone' => 'warn', 'title' => 'AV supplier quotation overdue', 'context' => 'NDI Summit · Due yesterday', 'time' => '08:50 AM'],
        ];
    }

    public static function quickActions(): array
    {
        return [
            ['label' => 'New Event', 'icon' => 'events'],
            ['label' => 'New Task', 'icon' => 'tasks'],
            ['label' => 'Add Contact', 'icon' => 'people'],
            ['label' => 'Add Supplier', 'icon' => 'supplier'],
            ['label' => 'New Contract', 'icon' => 'doc'],
            ['label' => 'New Payment', 'icon' => 'money'],
            ['label' => 'Upload Document', 'icon' => 'upload'],
            ['label' => 'Generate Report', 'icon' => 'report'],
            ['label' => 'Ask AI', 'icon' => 'ai'],
            ['label' => 'More', 'icon' => 'more'],
        ];
    }

    /** Mission Route — the company's journey, not one event's. */
    public static function missionRoute(): array
    {
        return [
            ['label' => 'Planning', 'count' => '5 Events', 'state' => 'ok'],
            ['label' => 'Production', 'count' => '6 Events', 'state' => 'ok'],
            ['label' => 'Build-Up', 'count' => '4 Events', 'state' => 'warn'],
            ['label' => 'Live', 'count' => '3 Events', 'state' => 'live'],
            ['label' => 'Close-Out', 'count' => '2 Events', 'state' => 'idle'],
            ['label' => 'Post Event', 'count' => '4 Events', 'state' => 'idle'],
        ];
    }

    public static function financial(): array
    {
        return [
            'usedPct' => 61,
            'rows' => [
                ['label' => 'Total Budget', 'value' => 'JD 2.30M'],
                ['label' => 'Committed', 'value' => 'JD 1.40M'],
                ['label' => 'Paid', 'value' => 'JD 820K'],
                ['label' => 'Remaining', 'value' => 'JD 490K'],
            ],
            'forecast' => ['label' => 'Profit Forecast', 'value' => 'JD 310K (21%)'],
        ];
    }

    public static function workload(): array
    {
        return [
            ['team' => 'Operations', 'pct' => 82],
            ['team' => 'Logistics', 'pct' => 74],
            ['team' => 'Creative', 'pct' => 91],
            ['team' => 'Finance', 'pct' => 68],
            ['team' => 'Registration', 'pct' => 88],
            ['team' => 'Production', 'pct' => 77],
        ];
    }

    public static function milestones(): array
    {
        return [
            ['label' => 'Venue Final Inspection', 'when' => 'Today', 'tone' => 'risk'],
            ['label' => 'Speaker Rehearsal', 'when' => 'Tomorrow', 'tone' => 'warn'],
            ['label' => 'Exhibition Build Starts', 'when' => 'Jul 30', 'tone' => 'info'],
            ['label' => 'Sponsorship Deadline', 'when' => 'Aug 1', 'tone' => 'info'],
            ['label' => 'VIP Dinner', 'when' => 'Aug 2', 'tone' => 'info'],
        ];
    }
}
