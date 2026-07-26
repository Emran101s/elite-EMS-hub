@props(['event'])
@php
    $tones = [
        'planning'  => ['text-cc-plan', 'bg-cc-plan'],
        'progress'  => ['text-cc-info', 'bg-cc-info'],
        'confirmed' => ['text-cc-ok', 'bg-cc-ok'],
        'risk'      => ['text-cc-risk', 'bg-cc-risk'],
    ];
    [$statusText, $statusDot] = $tones[$event['status']] ?? $tones['planning'];
    $ring = $event['health'] >= 85 ? 'text-cc-ok' : ($event['health'] >= 65 ? 'text-cc-info' : 'text-cc-risk');
    $r = 17; $c = 2 * M_PI * $r;
@endphp
{{-- A floating operational object, not a table row. Lifts on hover. --}}
<a href="#" class="group block rounded-2xl border border-cc-line bg-white p-4 transition duration-300 cc-lift-2
                   hover:-translate-y-1.5 hover:border-cc-gold hover:cc-lift-3">
    <div class="flex items-start justify-between gap-3">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-cc-mist px-2.5 py-1 text-[9.5px] font-bold uppercase tracking-[0.12em] {{ $statusText }}">
            <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>{{ $event['statusLabel'] }}
        </span>
        <x-canvas.icon name="grid" :size="15" class="shrink-0 text-cc-ink-3 transition group-hover:text-cc-gold" />
    </div>

    <h3 class="mt-2.5 truncate text-[15px] font-extrabold tracking-tight text-cc-navy">{{ $event['name'] }}</h3>

    <p class="mt-2 flex items-center gap-1.5 text-[11.5px] text-cc-ink-2"><x-canvas.icon name="cal" :size="13" class="text-cc-ink-3" />{{ $event['dates'] }}</p>
    <p class="mt-1 flex items-center gap-1.5 text-[11.5px] text-cc-ink-2"><x-canvas.icon name="pin" :size="13" class="text-cc-ink-3" />{{ $event['location'] }}</p>

    <div class="mt-3 flex items-center gap-3 border-t border-cc-line pt-3">
        <span class="relative grid h-11 w-11 shrink-0 place-items-center">
            <svg width="44" height="44" viewBox="0 0 44 44" class="-rotate-90">
                <circle cx="22" cy="22" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="4" class="text-cc-line" />
                <circle cx="22" cy="22" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"
                        class="{{ $ring }}" stroke-dasharray="{{ round($c, 1) }}" stroke-dashoffset="{{ round($c - $c * $event['health'] / 100, 1) }}" />
            </svg>
            <span class="absolute text-[10.5px] font-extrabold text-cc-navy">{{ $event['health'] }}%</span>
        </span>
        <div class="min-w-0 flex-1 space-y-1.5">
            <p class="flex items-center gap-1.5 text-[11px] text-cc-ink-2"><x-canvas.icon name="people" :size="12" class="text-cc-ink-3" /><b class="font-bold text-cc-navy">{{ $event['participants'] }}</b> Participants</p>
            <p class="flex items-center gap-1.5 text-[11px] text-cc-ink-2"><x-canvas.icon name="money" :size="12" class="text-cc-ink-3" /><b class="font-bold text-cc-navy">{{ $event['budget'] }}</b> Budget</p>
        </div>
    </div>

    <p class="mt-2 text-right text-[10.5px] font-bold {{ $event['footTone'] === 'ok' ? 'text-cc-ok' : 'text-cc-risk' }}">{{ $event['foot'] }}</p>
</a>
