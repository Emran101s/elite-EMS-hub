@props(['event', 'header'])

{{--
    Right Detail Panel — Agenda Overview for this pass (per the redesign
    brief: "implement Agenda/Overview first, keep extensibility for
    others"). Every figure reads an already-loaded relation or
    EventCommandHeader's own numbers; Recent Activity is the one new query
    this redesign adds, scoped to this event's own AuditLog rows.
--}}

@php
    $agendaPct = collect($header['meters'] ?? [])->firstWhere('key', 'agenda')['pct'] ?? null;
    $sessions = $event->agendaSessions;
    $missing = $sessions->reject->isSettled()->count();
    $speakersTotal = $event->speakers->count();
    $speakersConfirmed = $event->speakers->where('status', 'confirmed')->count();
    $critical = $header['critical'];

    $recent = \App\Models\AuditLog::where('event_id', $event->id)
        ->with('user')
        ->latest()
        ->take(3)
        ->get();
@endphp

<div class="hubx-panel">
    <div class="hubx-panel-head">
        <span class="hubx-panel-icon">
            <x-icon name="calendar" class="h-4 w-4" />
        </span>
        <div>
            <p class="eo-label !text-[9.5px]">Agenda</p>
            <p class="text-[14px] font-extrabold text-eo-text">Agenda Overview</p>
        </div>
    </div>

    <div class="hubx-panel-ring-wrap" style="--pr-pct: {{ $agendaPct ?? 0 }}">
        <div class="hubx-panel-ring-inner">
            <span class="text-[20px] font-extrabold text-eo-text">{{ $agendaPct !== null ? $agendaPct.'%' : '—' }}</span>
            <span class="text-[9px] font-bold uppercase tracking-wide text-eo-muted">Ready</span>
        </div>
    </div>

    <div class="mt-2">
        <div class="hubx-panel-metric-row">
            <x-icon name="clipboard" class="h-3.5 w-3.5 text-eo-muted" />
            <span class="text-[12px] font-bold text-eo-text">{{ $sessions->count() }}</span>
            <span class="text-[11px] text-eo-muted">Sessions</span>
        </div>
        <div class="hubx-panel-metric-row">
            <x-icon name="bell" class="h-3.5 w-3.5 text-eo-muted" />
            <span class="text-[12px] font-bold text-eo-text">{{ $missing }}</span>
            <span class="text-[11px] text-eo-muted">{{ str('Session')->plural($missing) }} not settled</span>
        </div>
        <div class="hubx-panel-metric-row">
            <x-icon name="users" class="h-3.5 w-3.5 text-eo-muted" />
            <span class="text-[12px] font-bold text-eo-text">{{ $event->attendees->count() }}</span>
            <span class="text-[11px] text-eo-muted">Total attendees</span>
        </div>
        <div class="hubx-panel-metric-row">
            <x-icon name="sparkles" class="h-3.5 w-3.5 text-eo-muted" />
            <span class="text-[12px] font-bold text-eo-text">{{ $speakersConfirmed }}/{{ $speakersTotal }}</span>
            <span class="text-[11px] text-eo-muted">Speakers confirmed</span>
        </div>
    </div>

    @if ($critical)
        <div class="hubx-panel-next">
            <p class="text-[9.5px] font-bold uppercase tracking-wide text-white/60">Next Action</p>
            <p class="mt-1 text-[13px] font-bold">{{ $critical['title'] }}</p>
            <p class="mt-0.5 text-[11px] text-white/60">{{ $critical['due'] }} · {{ $critical['level'] }} impact</p>
            <a href="{{ route('events.hub', [$event, 'tab' => $critical['tab']]) }}" wire:navigate
               class="mt-2.5 inline-flex items-center justify-center rounded-lg bg-white px-3 py-1.5 text-[11px] font-bold text-eo-navy-deep">
                {{ $critical['cta'] }}
            </a>
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <div class="mt-3">
            <p class="eo-label !text-[9.5px]">Recent Activity</p>
            @foreach ($recent as $entry)
                <div class="hubx-panel-activity-row">
                    <x-user-avatar :user="$entry->user" size="h-6 w-6" text="text-[9px]" />
                    <div class="min-w-0">
                        <p class="truncate text-[11.5px] font-semibold text-eo-text">{{ $entry->summary() }}</p>
                        <p class="text-[10px] text-eo-muted">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}" wire:navigate class="hubx-stack-viewall mt-2" style="color: var(--color-eo-teal-ink); background: var(--color-eo-teal-soft);">
        View Full Agenda →
    </a>
</div>
