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
                    {{-- Winning a deal opens an event, and that is where the
                         work actually starts. The row offered "Open event →"
                         whether the event had anything in it or not, so a win
                         nobody had picked up looked identical to one already
                         being delivered. Say which it is. --}}
                    @if ($deal->stage === 'won')
                        @php
                            $ev = $deal->event;
                            $empty = $ev && ! ($ev->budget_items_count || $ev->income_items_count || $ev->attendees_count);
                        @endphp
                        @if (! $ev)
                            <span class="mt-0.5 block text-[10px] font-semibold text-warning-ink">Won, but no event was opened for it</span>
                        @elseif ($empty)
                            <span class="mt-0.5 block text-[10px] font-semibold text-warning-ink">Event opened, nothing in it yet — no budget, income or people</span>
                        @endif
                    @endif
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
