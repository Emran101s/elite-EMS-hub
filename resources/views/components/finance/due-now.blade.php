@props(['receivables', 'currency'])

@php
    $money = fn (int $cents) => \App\Support\Money::abbreviated($cents, $currency);
@endphp

<div class="overflow-hidden rounded-lg border border-line bg-white">
    <div class="border-b border-line px-4 py-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Due now</p>
    </div>
    <div class="divide-y divide-line">
        @forelse ($receivables->take(6) as $p)
            @php $overdue = $p->due_on?->isPast() && $p->outstandingCents() > 0; @endphp
            <a href="{{ route('events.hub', [$p->event, 'tab' => 'contract']) }}" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-page">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $overdue ? 'bg-danger' : 'bg-warning' }}"></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[12px] font-semibold text-ink">{{ $p->event?->name }}</span>
                    <span class="block truncate text-[10.5px] text-muted">{{ \Illuminate\Support\Str::limit($p->label, 34) }}</span>
                </span>
                <span class="shrink-0 text-end text-[11.5px] font-bold tabular-nums">{{ $money($p->outstandingCents()) }}</span>
            </a>
        @empty
            <p class="px-4 py-5 text-center text-[11.5px] text-muted">Every instalment is settled.</p>
        @endforelse
    </div>
</div>
