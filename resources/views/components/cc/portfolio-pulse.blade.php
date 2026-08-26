@props(['stages' => []])

@php
    // Lifecycle composition — only stages that actually carry events, so the
    // bar and the stat row read the real shape of the book, not every empty
    // state in the workflow. This is the one at-a-glance portfolio view the
    // page doesn't get anywhere else (the KPI strip counts, it doesn't shape).
    $live = collect($stages)->filter(fn ($s) => ($s['count'] ?? 0) > 0)->values();
    $total = (int) $live->sum('count');
    $lead = $live->sortByDesc('count')->first();
    $noun = \Illuminate\Support\Str::plural('event', $total);
    $leadNote = $lead ? ' · most in '.$lead['label'] : '';
@endphp

<div class="overflow-hidden rounded-lg border border-line bg-white shadow-raise">
    <div class="flex flex-wrap items-start justify-between gap-3 px-5 pt-5">
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">The book · by stage</p>
            <div class="mt-1.5 flex items-end gap-2">
                <span class="pf text-[36px] font-bold leading-none text-ink">{{ $total }}</span>
                <span class="pb-1 text-[13px] font-semibold text-muted">{{ $noun }} in flight{{ $leadNote }}</span>
            </div>
        </div>
        <a href="{{ route('events.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">
            Event Portfolio →
        </a>
    </div>

    @if ($total > 0)
        {{-- Segmented composition bar — the hero read: the whole book at once, coloured by lifecycle stage. --}}
        <div class="mt-4 flex h-3 w-full overflow-hidden bg-page" title="Portfolio by lifecycle stage">
            @foreach ($live as $s)
                <span class="h-full" style="width: {{ round($s['count'] / $total * 100, 2) }}%; background: {{ $s['hex'] }}"></span>
            @endforeach
        </div>

        {{-- Stage stat cards — the legend, given weight so the band reads as a deliberate command surface. --}}
        <div class="mt-4 flex flex-wrap border-t border-line">
            @foreach ($live as $s)
                <div class="min-w-[132px] flex-1 border-r border-line p-4 last:border-r-0">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $s['hex'] }}"></span>
                        <span class="truncate text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">{{ $s['label'] }}</span>
                    </div>
                    <div class="mt-1.5 flex items-baseline gap-1.5">
                        <span class="text-[24px] font-extrabold tabular-nums text-ink">{{ $s['count'] }}</span>
                        <span class="text-[11px] font-semibold text-muted">{{ round($s['count'] / $total * 100) }}%</span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="px-5 pb-5 pt-4 text-[12.5px] text-muted">No active events on the book yet.</p>
    @endif
</div>
