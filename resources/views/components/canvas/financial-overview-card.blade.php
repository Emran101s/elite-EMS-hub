@props(['data' => []])
@php $r = 34; $c = 2 * M_PI * $r; $pct = $data['usedPct'] ?? 0; @endphp
<section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">Financial Overview</h2>
            <p class="mt-0.5 text-[11px] text-cc-ink-3">This Month</p>
        </div>
        <a href="{{ route('finance.index') }}" class="shrink-0 text-[11px] font-bold text-cc-blue transition hover:text-cc-navy">View Details →</a>
    </div>

    <div class="mt-4 flex items-center gap-5">
        <span class="relative grid h-[92px] w-[92px] shrink-0 place-items-center">
            <svg width="92" height="92" viewBox="0 0 92 92" class="-rotate-90">
                <circle cx="46" cy="46" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="10" class="text-cc-line" />
                <circle cx="46" cy="46" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round"
                        class="text-cc-navy" stroke-dasharray="{{ round($c, 1) }}" stroke-dashoffset="{{ round($c - $c * $pct / 100, 1) }}" />
            </svg>
            <span class="absolute text-center">
                <span class="block text-[17px] font-extrabold leading-none text-cc-navy">{{ $pct }}%</span>
                <span class="mt-1 block text-[8.5px] font-semibold text-cc-ink-3">Budget Used</span>
            </span>
        </span>

        <dl class="min-w-0 flex-1 space-y-2">
            @foreach ($data['rows'] ?? [] as $row)
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="truncate text-[11.5px] text-cc-ink-2">{{ $row['label'] }}</dt>
                    <dd class="shrink-0 text-[12.5px] font-bold tabular-nums text-cc-navy">{{ $row['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    @if (! empty($data['forecast']))
        <div class="mt-4 flex items-baseline justify-between gap-3 rounded-xl bg-cc-mist px-3 py-2.5">
            <span class="text-[11.5px] font-semibold text-cc-ink-2">{{ $data['forecast']['label'] }}</span>
            <span class="text-[13px] font-extrabold tabular-nums text-cc-ok">{{ $data['forecast']['value'] }}</span>
        </div>
    @endif
</section>
