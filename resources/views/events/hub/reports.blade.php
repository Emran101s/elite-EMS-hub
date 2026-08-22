@php
    $moduleHex = \App\Models\Event::moduleColor('reports');
@endphp

{{-- Honest landing — no fake report cards. Point people at the PDFs that already exist. --}}
<div class="space-y-4">
    <x-stat-strip :stats="[
        ['Budget', 'PDF', 'currency', null, null, 'Cost & margin snapshot'],
        ['Rooming', 'PDF', 'home', null, null, 'Named rooms by hotel'],
        ['Layout', 'PDF', 'building', null, null, 'Per-room floor plan'],
        ['Equipment', 'PDF', 'grid', null, null, 'AV prep sheets'],
    ]" />

    <x-empty icon="chart" title="Full report suite is next"
             hint="Event summary, supplier, task, risk, attendance, sponsor and post-event packs — branded with this event's logo and theme — will land here. Until then, export what each module already produces.">
        <x-slot:actions>
            <a href="{{ route('events.budget.pdf', $event) }}" target="_blank" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Budget PDF</a>
            <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" wire:navigate class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Open Budget →</a>
            <a href="{{ route('events.hub', [$event, 'tab' => 'accommodation']) }}" wire:navigate class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Stay / rooming →</a>
        </x-slot:actions>
    </x-empty>

    <div class="divide-y divide-line overflow-hidden rounded-lg border border-line bg-white">
        <div class="flex items-center gap-3 px-4 py-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background: {{ $moduleHex }}">
                <x-icon name="chart" class="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-ink">Available now</p>
                <p class="text-eyebrow text-muted">Exports from modules that already ship a document</p>
            </div>
        </div>
        @foreach ([
            ['Budget summary', 'Cost, actuals and margin for this event', route('events.budget.pdf', $event), 'budget'],
            ['Venue layouts', 'Open a room in Venue, then export its layout PDF', route('events.hub', [$event, 'tab' => 'venue']), 'venue'],
            ['Rooming list', 'Named guests by hotel block — from Stay', route('events.hub', [$event, 'tab' => 'accommodation']), 'accommodation'],
        ] as [$label, $hint, $href, $mod])
            <a href="{{ $href }}" @if (str_ends_with($href, '.pdf') || str_contains($href, '/pdf')) target="_blank" @else wire:navigate @endif
               class="flex items-center gap-3 px-4 py-3 transition hover:bg-page">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ \App\Models\Event::moduleColor($mod) }}"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-ink">{{ $label }}</span>
                    <span class="block text-eyebrow text-muted">{{ $hint }}</span>
                </span>
                <span class="text-eyebrow font-bold text-muted">Open →</span>
            </a>
        @endforeach
    </div>
</div>
