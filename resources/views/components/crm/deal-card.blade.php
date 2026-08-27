@props(['deal', 'selected' => false])

@php
    $order = \App\Models\Deal::OPEN;
    $i = array_search($deal->stage, $order, true);
    $next = $order[$i + 1] ?? 'won';
    $unpriced = $next === 'won' && ! $deal->acceptedProposal();
    $moveLabel = $next === 'won' ? '✓ Mark won' : '→ '.\App\Models\Deal::STAGES[$next][0];
    $money = fn (int $cents, string $cur = 'JOD') => \App\Support\Money::abbreviated($cents, $cur);
@endphp

<div wire:key="deal-{{ $deal->id }}" data-deal="{{ $deal->id }}"
     @class([
         'mb-3 rounded-lg border bg-white p-3.5 transition',
         'border-gold-400 ring-1 ring-gold-400/40 shadow-float' => $selected,
         'border-line hover:-translate-y-0.5 hover:border-gold-300 hover:shadow-raise' => ! $selected,
     ])>
    <button type="button" wire:click="select({{ $deal->id }})" class="block w-full text-left">
        <span class="flex items-start gap-2">
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[13px] font-bold leading-tight text-ink">{{ $deal->title }}</span>
                <span class="mt-1 block truncate text-[10.5px] text-muted">{{ $deal->client?->name }}</span>
            </span>
            @if ($deal->isStale())
                <span class="shrink-0 rounded-full bg-danger-soft px-1.5 py-0.5 text-[9px] font-bold text-danger-ink" title="Past its expected close date">STALE</span>
            @endif
        </span>

        <span class="mt-2.5 flex items-baseline justify-between gap-2">
            <span class="text-[15px] font-bold text-ink">{{ $money($deal->value_cents, $deal->currency) }}</span>
            <span class="text-[10.5px] font-bold tabular-nums text-muted">{{ $deal->probability }}%</span>
        </span>
        <span class="mt-1.5 block h-1 overflow-hidden rounded-full bg-page">
            <span class="block h-full rounded-full" style="width: {{ $deal->probability }}%; background: {{ $deal->stageHex() }}"></span>
        </span>

        <span class="mt-2.5 flex items-center gap-2 border-t border-line pt-2 text-[10.5px] text-muted">
            @if ($deal->owner)
                <x-user-avatar :user="$deal->owner" size="h-5 w-5" />
            @endif
            <span class="truncate">{{ $deal->contact?->name ?? 'No contact' }}</span>
            <span class="ml-auto shrink-0 tabular-nums">{{ $deal->expected_close_on?->format('j M') ?? '—' }}</span>
        </span>
    </button>

    <div class="mt-2.5 flex items-center gap-1 border-t border-line pt-2.5">
        @if ($unpriced)
            <x-confirm title="Win without an agreed figure?"
                       body="No accepted proposal on this deal, so the event opens with an empty budget and nothing to invoice from. Draft the offer first if the client has not signed one off."
                       confirm="Win anyway" cancel="Not yet" tone="warn"
                       run="$wire.moveTo({{ $deal->id }}, 'won')"
                       class="flex-1 rounded-md bg-page py-1.5 text-[11px] font-bold text-ink transition hover:bg-navy-900 hover:text-white">{{ $moveLabel }}</x-confirm>
        @else
            <button type="button" wire:click="moveTo({{ $deal->id }}, '{{ $next }}')"
                    class="flex-1 rounded-md bg-page py-1.5 text-[11px] font-bold text-ink transition hover:bg-navy-900 hover:text-white">
                {{ $moveLabel }}
            </button>
        @endif
        <button type="button" wire:click="moveTo({{ $deal->id }}, 'lost')" title="Mark lost"
                class="grid h-7 w-7 place-items-center rounded-md text-muted transition hover:bg-danger-soft hover:text-danger-ink">✕</button>
    </div>
</div>
