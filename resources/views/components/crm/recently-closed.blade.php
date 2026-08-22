@props(['closed'])

@php
    $money = fn (int $cents, string $cur = 'JOD') => \App\Support\Money::abbreviated($cents, $cur);
@endphp

<div class="mt-5">
    <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Recently closed</p>
    <div class="divide-y divide-line rounded-lg border border-line bg-white">
        @foreach ($closed as $deal)
            <div class="flex items-center gap-3 px-4 py-2.5">
                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $deal->stageHex() }}"></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[12.5px] font-semibold text-ink">{{ $deal->title }}</span>
                    <span class="block truncate text-[10.5px] text-muted">
                        {{ $deal->client?->name }}
                        @if ($deal->stage === 'lost' && $deal->lost_reason) · {{ $deal->lost_reason }} @endif
                    </span>
                </span>
                <span class="shrink-0 text-[11px] font-bold tabular-nums text-ink">{{ $money($deal->value_cents, $deal->currency) }}</span>
                @if ($deal->event)
                    <a href="{{ route('events.hub', $deal->event) }}" class="shrink-0 rounded-full border border-line bg-white px-2.5 py-1 text-[11px] font-bold text-ink transition hover:border-navy-300">Open event →</a>
                @else
                    <button type="button" wire:click="reopen({{ $deal->id }})" class="shrink-0 rounded-full border border-line bg-white px-2.5 py-1 text-[11px] font-bold text-ink transition hover:border-navy-300">Reopen</button>
                @endif
            </div>
        @endforeach
    </div>
</div>
