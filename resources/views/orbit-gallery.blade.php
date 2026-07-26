<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ORBIT gallery — live components</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background: var(--abyss); color: var(--ink); font-family: var(--font-ui); margin: 0; padding: 40px 32px 96px; }
        .wrap { max-width: 1080px; margin: 0 auto; }
        .sec { margin-top: 40px; }
        .sec > .o-eyebrow { display: block; margin-bottom: 14px; }
        .rowflex { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .g3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .g4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .head { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
        h1 { font: var(--t-display-2); letter-spacing: -.015em; margin: 4px 0 8px; }
        .note { color: var(--ink-2); font: var(--t-body); max-width: 64ch; margin: 0; }
        @media (max-width: 820px) { .g3, .g4 { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
<x-orbit.sprite />
<div class="wrap">
    <div class="head">
        <div>
            <span class="o-eyebrow">Elite Event Hub · ORBIT</span>
            <h1>Component gallery</h1>
            <p class="note">
                Rendered from the real Blade components in <code>resources/views/components/orbit/</code>.
                The spec is <code>orbit-system.html</code> — served at <a href="/design" style="color:var(--gold)">/design</a>.
                Every new component lands here in the commit that creates it.
            </p>
        </div>
        <button class="o-btn o-btn--solid" x-data @click="$store.theme.toggle()">
            Toggle theme
        </button>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Buttons</span>
        <div class="rowflex">
            <x-orbit.btn variant="gold">Create event</x-orbit.btn>
            <x-orbit.btn variant="dark">Dark</x-orbit.btn>
            <x-orbit.btn variant="solid">Solid</x-orbit.btn>
            <x-orbit.btn variant="ghost">Ghost</x-orbit.btn>
            <x-orbit.btn variant="quiet">Quiet</x-orbit.btn>
            <x-orbit.btn variant="danger">Delete</x-orbit.btn>
            <x-orbit.btn variant="solid" size="sm">Small</x-orbit.btn>
            <x-orbit.btn variant="gold" size="lg">Large</x-orbit.btn>
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Status badges — colour comes from Tone, never a literal</span>
        <div class="rowflex">
            <x-orbit.badge :tone="\App\Support\Tone::forTask('done')->value">Paid</x-orbit.badge>
            <x-orbit.badge :tone="\App\Support\Tone::forTask('in_progress')->value">In progress</x-orbit.badge>
            <x-orbit.badge :tone="\App\Support\Tone::forTask('planned')->value">Planned</x-orbit.badge>
            <x-orbit.badge :tone="\App\Support\Tone::forTask('pending')->value">Needs approval</x-orbit.badge>
            <x-orbit.badge :tone="\App\Support\Tone::forTask('in_progress', overdue: true)->value" pulse>Overdue</x-orbit.badge>
            <x-orbit.badge tone="gold">VIP</x-orbit.badge>
            <x-orbit.badge>Draft</x-orbit.badge>
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Rings — single values only</span>
        <div class="rowflex" style="gap:34px">
            <x-orbit.ring :value="91" label="Ready" />
            <x-orbit.ring :value="77" label="Watch" />
            <x-orbit.ring :value="59" label="Behind" />
            <x-orbit.ring :value="64" tone="gold" label="Budget" />
            <x-orbit.ring :value="60" :size="52" :stroke="4" :label="null" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Meter — splits. This is what replaces a donut.</span>
        <div style="max-width:520px">
            <x-orbit.meter tall legend :segments="[
                ['value' => 214498, 'tone' => 'plasma', 'label' => 'Spent', 'display' => 'JD 214,498 (61%)'],
                ['value' => 98700, 'tone' => 'ion', 'label' => 'Committed', 'display' => 'JD 98,700 (28%)'],
                ['value' => 36802, 'tone' => 'vital', 'label' => 'Remaining', 'display' => 'JD 36,802 (11%)'],
            ]" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Stat tiles</span>
        <div class="g4">
            <x-orbit.stat label="Participants" value="650" unit="/ 800" :delta="['dir'=>'up','text'=>'12']" foot="new this week" />
            <x-orbit.stat label="Days left" value="105" foot="Nov 12, 2026" />
            <x-orbit.stat label="Open tasks" value="17" tone="critical" :delta="['dir'=>'down','text'=>'3']" foot="overdue" />
            <x-orbit.stat label="Suppliers" value="42" unit="2 issues" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">KPI strip — chrome, so it stays dark in both themes</span>
        <x-orbit.kpi-strip :items="[
            ['k' => 'Participants', 'v' => '650', 'f' => 'new this week', 'delta' => ['dir' => 'up', 'text' => '12']],
            ['k' => 'Budget used', 'v' => '61%', 'f' => 'of JD 350,000'],
            ['k' => 'Open tasks', 'v' => '17', 'f' => 'overdue', 'delta' => ['dir' => 'down', 'text' => '3'], 'tone' => 'critical'],
            ['k' => 'Days to go', 'v' => '105', 'f' => 'Nov 8, 2026'],
        ]">
            <x-slot:cta><x-orbit.btn variant="solid" size="sm">Open hub</x-orbit.btn></x-slot:cta>
        </x-orbit.kpi-strip>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Arc navigation — positions are computed, never hand-placed</span>
        <div style="display:flex;gap:26px;align-items:center">
            <x-orbit.nav-arc current="tasks"
                :inner="[
                    ['key' => 'overview', 'label' => 'Overview', 'icon' => 'home'],
                    ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'task', 'count' => 17],
                    ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'cal'],
                    ['key' => 'budget', 'label' => 'Budget', 'icon' => 'money'],
                    ['key' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'truck', 'count' => 2],
                ]"
                :outer="[
                    ['key' => 'venue', 'label' => 'Venue', 'icon' => 'venue'],
                    ['key' => 'speakers', 'label' => 'Speakers', 'icon' => 'mic'],
                    ['key' => 'sponsors', 'label' => 'Sponsors', 'icon' => 'star'],
                    ['key' => 'docs', 'label' => 'Documents', 'icon' => 'doc'],
                ]">
                <x-slot:core>
                    <span class="o-eyebrow" style="color:var(--chrome-ink-3)">Summit</span>
                    <span class="o-metric-sm" style="color:var(--chrome-ink)">105d</span>
                </x-slot:core>
            </x-orbit.nav-arc>
            <p class="o-mute" style="max-width:38ch;margin:0">
                Inner ring: the five modules touched daily, at r=150 in 30° steps.
                Outer ring: structure, at r=190 in 32° steps. The angles are identical
                on every event because the component computes them.
            </p>
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Dock — nine slots, last is always AI</span>
        <x-orbit.dock current="tasks" :items="[
            ['key' => 'home', 'label' => 'Home', 'icon' => 'home'],
            ['key' => 'events', 'label' => 'Events', 'icon' => 'grid'],
            ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'task', 'count' => 17],
            ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'cal'],
            ['key' => 'money', 'label' => 'Finance', 'icon' => 'money'],
            ['key' => 'people', 'label' => 'People', 'icon' => 'users'],
            ['key' => 'docs', 'label' => 'Docs', 'icon' => 'doc'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart'],
            ['key' => 'ai', 'label' => 'AI', 'icon' => 'spark'],
        ]" />
    </div>

    <div class="sec">
        <span class="o-eyebrow">Task cards — overdue is the only thing allowed to pulse</span>
        <div class="g3">
            <x-orbit.task-card title="Book main stage lighting" module="Venue" due="Jul 20, 2026"
                               status="in_progress" overdue :progress="20"
                               :assignees="['Alyona Dolgopolova']" :extra="2" />
            <x-orbit.task-card title="Confirm interpreter booths" module="Agenda" due="Aug 2, 2026"
                               status="in_progress" :progress="60" :assignees="['Emran Itan']" />
            <x-orbit.task-card title="Sign catering agreement" module="Suppliers" due="Aug 14, 2026"
                               status="pending" :progress="0" :assignees="['Nour Haddad', 'Sara Malki']" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Topbar, search and the event chip</span>
        <x-orbit.topbar style="border-radius:var(--r-lg);border:1px solid var(--rim)">
            <x-orbit.search />
            <x-orbit.event-chip :lines="['FWP', '26']" />
            <x-orbit.avatar name="Emran Itan" />
        </x-orbit.topbar>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Greeting + Quick Add — open with something actionable</span>
        <x-orbit.card>
            <x-orbit.greeting heading="Good morning, Emran">
                <x-slot:summary>You have <b>3 approvals</b> waiting and <b>2 suppliers</b> that need chasing before Friday.</x-slot:summary>
            </x-orbit.greeting>
            <div style="margin-top:var(--o-5)">
                <x-orbit.quick-add :items="[
                    ['label' => 'Task', 'icon' => 'task'],
                    ['label' => 'Session', 'icon' => 'cal'],
                    ['label' => 'Supplier', 'icon' => 'truck'],
                    ['label' => 'Expense', 'icon' => 'money'],
                    ['label' => 'Note', 'icon' => 'note'],
                ]" />
            </div>
        </x-orbit.card>
    </div>

    <div class="sec">
        <span class="o-eyebrow">List rows — one template for venues, suppliers, attendees, line items</span>
        <x-orbit.list>
            <x-orbit.row icon="venue" name="The St. Regis Amman" :meta="['12 sessions', '650 capacity', 'Amman']" amount="JD 84,000">
                <x-orbit.badge tone="vital">Confirmed</x-orbit.badge>
            </x-orbit.row>
            <x-orbit.row icon="truck" name="Royal Jordanian Transport" :meta="['18 movements', '4 vehicles']" amount="JD 12,400">
                <x-orbit.badge tone="flare">Awaiting quote</x-orbit.badge>
            </x-orbit.row>
            <x-orbit.row icon="mic" name="Dr. Layla Haddad" :meta="['Keynote', 'Day 2 · 09:30']" amount="—">
                <x-orbit.badge tone="ion">Invited</x-orbit.badge>
            </x-orbit.row>
        </x-orbit.list>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Data table — money right-aligned in the data face</span>
        <x-orbit.card :pad="false">
            <x-orbit.table
                :columns="['Category', 'Owner', ['label' => 'Budget', 'num' => true], ['label' => 'Spent', 'num' => true]]"
                :rows="[
                    ['Venue and production', 'Emran Itan', ['v' => 'JD 140,000', 'num' => true], ['v' => 'JD 121,480', 'num' => true]],
                    ['Hospitality', 'Nour Haddad', ['v' => 'JD 86,000', 'num' => true], ['v' => 'JD 52,900', 'num' => true]],
                    ['Transport', 'Sara Malki', ['v' => 'JD 42,000', 'num' => true], ['v' => 'JD 12,400', 'num' => true]],
                ]" />
        </x-orbit.card>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Form fields</span>
        <div class="g3">
            <x-orbit.field label="Event name" value="The First World Public Summit" />
            <x-orbit.field label="Budget" type="number" affix="JD" value="350000" help="Excludes VAT." />
            <x-orbit.field label="Stage" type="select" :options="['planning' => 'Planning', 'production' => 'Production', 'live' => 'Live']" selected="planning" />
        </div>
        <div class="rowflex" style="margin-top:16px">
            <x-orbit.chips>
                <x-orbit.chip>Draft the supplier brief</x-orbit.chip>
                <x-orbit.chip>What is at risk?</x-orbit.chip>
            </x-orbit.chips>
        </div>
        <div class="rowflex" style="margin-top:16px">
            <x-orbit.seg :options="['cards' => 'Cards', 'list' => 'List', 'calendar' => 'Calendar']" selected="cards" />
            <x-orbit.tabs :options="['all' => 'All', 'overdue' => 'Overdue', 'today' => 'Today', 'week' => 'This week']" selected="overdue" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Alerts and the activity feed</span>
        <div class="g3" style="grid-template-columns:1fr 1fr">
            <x-orbit.card :pad="false">
                <x-orbit.feed style="padding:var(--o-3)">
                    <x-orbit.alert tone="critical" icon="warn" title="Catering contract is unsigned" sub="Due in 3 days" time="2h" />
                    <x-orbit.alert tone="vital" icon="doc" title="Venue agreement countersigned" sub="St. Regis Amman" time="5h" />
                    <x-orbit.alert tone="flare" icon="clock" title="Transport quote expires Friday" sub="Royal Jordanian" time="1d" />
                </x-orbit.feed>
            </x-orbit.card>
            <x-orbit.ai-panel
                :insights="[
                    ['title' => 'Transport is the critical path', 'sub' => '18 movements still unassigned 105 days out.', 'tone' => 'flare', 'icon' => 'warn'],
                    ['title' => 'Budget is tracking under', 'sub' => 'JD 36,802 remaining at 61% spent.', 'tone' => 'vital', 'icon' => 'money'],
                ]"
                :chips="['Draft the supplier brief', 'What is at risk?', 'Summarise this week']" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Pulse rail and the delivery timeline</span>
        <div class="g3" style="grid-template-columns:1fr 2fr">
            <x-orbit.card title="Event Pulse">
                <x-orbit.pulse :metrics="[
                    ['label' => 'Tasks on time', 'value' => '88%', 'tone' => 'vital'],
                    ['label' => 'Budget health', 'value' => '61%', 'tone' => 'ion'],
                    ['label' => 'Supplier risk', 'value' => '2', 'tone' => 'flare'],
                    ['label' => 'Overdue', 'value' => '3', 'tone' => 'critical'],
                ]" />
            </x-orbit.card>
            <x-orbit.card title="Delivery journey">
                <x-orbit.gantt :today="62"
                    :scale="['Jul', 'Aug', 'Sep', 'Oct', 'Nov']"
                    :bands="[
                        ['label' => 'Planning', 'start' => 0, 'end' => 38, 'tone' => 'plasma'],
                        ['label' => 'Production', 'start' => 30, 'end' => 78, 'tone' => 'ion'],
                        ['label' => 'Show days', 'start' => 78, 'end' => 92, 'tone' => 'gold', 'note' => 'Nov 8–12'],
                        ['label' => 'Close-out', 'start' => 92, 'end' => 100, 'tone' => 'vital'],
                    ]" />
            </x-orbit.card>
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Empty state — it teaches the concept, it never says "No data"</span>
        <x-orbit.empty icon="truck" title="No movements scheduled yet"
                       body="A movement is one vehicle taking named guests from A to B at a time. Import your arrivals sheet and we'll build the first day for you.">
            <div class="rowflex" style="justify-content:center">
                <x-orbit.btn variant="gold">Import arrivals</x-orbit.btn>
                <x-orbit.btn variant="ghost">Add one manually</x-orbit.btn>
            </div>
        </x-orbit.empty>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Modal and toast</span>
        <div class="rowflex" style="align-items:flex-start;gap:20px">
            <x-orbit.modal title="Archive this event?">
                <p class="o-mute" style="margin:0">It disappears from lists and the Operations Hub. Nothing is deleted, and you can restore it at any time.</p>
                <x-slot:foot>
                    <x-orbit.btn variant="quiet">Cancel</x-orbit.btn>
                    <x-orbit.btn variant="gold">Archive</x-orbit.btn>
                </x-slot:foot>
            </x-orbit.modal>
            <div style="display:grid;gap:10px">
                <x-orbit.toast tone="vital" icon="doc">Contract sent to the client.</x-orbit.toast>
                <x-orbit.toast tone="critical" icon="warn">Couldn't reach the supplier.</x-orbit.toast>
            </div>
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Cards — gravity decides what matters</span>
        <div class="g3">
            <x-orbit.card gravity="hero" title="Event Hub">
                <p class="o-mute" style="margin:0">Exactly one card per screen carries <code>data-gravity="hero"</code>. It answers why someone opened the page.</p>
            </x-orbit.card>
            <x-orbit.card accent="ion" title="Budget">
                <p class="o-mute" style="margin:0">A standard card. The accent bar is the only colour it gets.</p>
            </x-orbit.card>
            <x-orbit.card gravity="ambient" title="Archived">
                <p class="o-dim" style="margin:0">Ambient — present, but out of the way.</p>
            </x-orbit.card>
        </div>
    </div>
</div>
</body>
</html>
