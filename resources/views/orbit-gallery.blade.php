<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ORBIT Gallery — live components</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700|instrument-serif:400,400i|jetbrains-mono:400,500,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--abyss); color: var(--ink); font-family: var(--font-ui); margin: 0; padding: 48px; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .sec { margin-top: 44px; }
        .sec > .o-eyebrow { display:block; margin-bottom: 16px; }
        .rowflex { display:flex; flex-wrap:wrap; gap:14px; align-items:center; }
        .grid4 { display:grid; grid-template-columns: repeat(4, 1fr); gap:14px; }
        h1.display { font: var(--t-display-2); letter-spacing:-.015em; margin:0 0 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <span class="o-eyebrow o-eyebrow--solar">Elite Event Hub · ORBIT</span>
    <h1 class="display">Live component gallery</h1>
    <p class="o-mute" style="max-width:60ch">Rendered from the real Blade components in <code>components/orbit/</code> — this is the source of truth the app assembles screens from, not the static design HTML.</p>

    <div class="sec">
        <span class="o-eyebrow">Buttons</span>
        <div class="rowflex">
            <x-orbit.btn variant="solar">Create event</x-orbit.btn>
            <x-orbit.btn variant="solid">Solid</x-orbit.btn>
            <x-orbit.btn variant="ghost">Ghost</x-orbit.btn>
            <x-orbit.btn variant="quiet">Quiet</x-orbit.btn>
            <x-orbit.btn variant="danger">Delete</x-orbit.btn>
            <x-orbit.btn variant="solid" size="sm">Small</x-orbit.btn>
            <x-orbit.btn variant="solar" size="lg">Large</x-orbit.btn>
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Status badges</span>
        <div class="rowflex">
            <x-orbit.badge tone="vital">Paid</x-orbit.badge>
            <x-orbit.badge tone="ion">In progress</x-orbit.badge>
            <x-orbit.badge tone="plasma">Planned</x-orbit.badge>
            <x-orbit.badge tone="flare">Pending</x-orbit.badge>
            <x-orbit.badge tone="critical" pulse>Overdue</x-orbit.badge>
            <x-orbit.badge tone="solar">VIP</x-orbit.badge>
            <x-orbit.badge>Neutral</x-orbit.badge>
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Rings — single-value health</span>
        <div class="rowflex" style="gap:32px">
            <x-orbit.ring :value="91" tone="vital" label="Ready" />
            <x-orbit.ring :value="77" tone="solar" label="Watch" />
            <x-orbit.ring :value="59" tone="critical" label="Behind" />
            <x-orbit.ring :value="60" tone="ion" :size="52" :stroke="4" :label="null" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Meter — segmented split (never a donut)</span>
        <div style="max-width:520px">
            <x-orbit.meter tall legend :segments="[
                ['value' => 214, 'tone' => 'ion', 'label' => 'Spent', 'display' => 'JD 214K'],
                ['value' => 61, 'tone' => 'flare', 'label' => 'Committed', 'display' => 'JD 61K'],
                ['value' => 75, 'tone' => 'vital', 'label' => 'Remaining', 'display' => 'JD 75K'],
            ]" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Stat tiles</span>
        <div class="grid4">
            <x-orbit.stat label="Participants" value="650" unit="/ 800" :delta="['dir'=>'up','text'=>'12']" foot="new this week" />
            <x-orbit.stat label="Days left" value="105" foot="Nov 12, 2026" />
            <x-orbit.stat label="Open tasks" value="17" tone="critical" :delta="['dir'=>'down','text'=>'3']" foot="overdue" />
            <x-orbit.stat label="Suppliers" value="42" />
        </div>
    </div>

    <div class="sec">
        <span class="o-eyebrow">Cards — gravity scale</span>
        <div class="grid4" style="grid-template-columns:repeat(3,1fr)">
            <x-orbit.card gravity="hero" title="Command Center">
                <p class="o-mute" style="margin:0">The one card that matters most on the screen. Solar glow, raised.</p>
            </x-orbit.card>
            <x-orbit.card accent="ion" title="Budget">
                <p class="o-mute" style="margin:0">A standard card with an ion accent bar.</p>
            </x-orbit.card>
            <x-orbit.card gravity="ambient" title="Archived">
                <p class="o-dim" style="margin:0">Ambient — dashed, transparent, out of the way.</p>
            </x-orbit.card>
        </div>
    </div>
</div>
</body>
</html>
