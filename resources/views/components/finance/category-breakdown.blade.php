@props(['categories', 'currency'])

@php
    $money = fn (int $cents) => \App\Support\Money::abbreviated($cents, $currency);
    $top = (int) $categories->max('cost') ?: 1;
@endphp

<div class="rounded-lg border border-line bg-white p-5">
    <p class="mb-3 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Where the money goes</p>
    <div class="space-y-2">
        @foreach ($categories->take(8) as $c)
            <div>
                <div class="flex items-baseline justify-between gap-2 text-[11.5px]">
                    <span class="truncate text-ink">{{ $c['label'] }}</span>
                    <span class="shrink-0 font-bold tabular-nums">{{ $money($c['cost']) }}</span>
                </div>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-page">
                    <div class="h-full rounded-full bg-gold-500" style="width: {{ round($c['cost'] / $top * 100) }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
