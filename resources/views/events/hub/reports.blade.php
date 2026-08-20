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
             hint="Event summary, supplier, task, risk, attendance, sponsor and post-event packs — branded with this event’s logo and theme — will land here. Until then, export what each module already produces.">
        <x-slot:actions>
            <x-eo.button size="sm" href="{{ route('events.budget.pdf', $event) }}" target="_blank">Budget PDF</x-eo.button>
            <x-eo.button variant="ghost" size="sm" href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" wire:navigate>Open Budget →</x-eo.button>
            <x-eo.button variant="ghost" size="sm" href="{{ route('events.hub', [$event, 'tab' => 'accommodation']) }}" wire:navigate>Stay / rooming →</x-eo.button>
        </x-slot:actions>
    </x-empty>

    <div class="eo-soft-card divide-y divide-eo-line overflow-hidden">
        <div class="flex items-center gap-3 px-4 py-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background: {{ $moduleHex }}">
                <x-icon name="chart" class="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-eo-text">Available now</p>
                <p class="text-eyebrow text-eo-muted">Exports from modules that already ship a document</p>
            </div>
        </div>
        @foreach ([
            ['Budget summary', 'Cost, actuals and margin for this event', route('events.budget.pdf', $event), 'budget'],
            ['Venue layouts', 'Open a room in Venue, then export its layout PDF', route('events.hub', [$event, 'tab' => 'venue']), 'venue'],
            ['Rooming list', 'Named guests by hotel block — from Stay', route('events.hub', [$event, 'tab' => 'accommodation']), 'accommodation'],
        ] as [$label, $hint, $href, $mod])
            <a href="{{ $href }}" @if (str_ends_with($href, '.pdf') || str_contains($href, '/pdf')) target="_blank" @else wire:navigate @endif
               class="flex items-center gap-3 px-4 py-3 transition hover:bg-eo-workspace/50">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ \App\Models\Event::moduleColor($mod) }}"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-eo-text">{{ $label }}</span>
                    <span class="block text-eyebrow text-eo-muted">{{ $hint }}</span>
                </span>
                <span class="text-eyebrow font-bold text-eo-muted">Open →</span>
            </a>
        @endforeach
    </div>
</div>
