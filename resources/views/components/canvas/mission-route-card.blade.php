@props(['phases' => []])
@php $states = ['ok' => ['bg-cc-ok', 'check'], 'warn' => ['bg-cc-warn', 'clock'], 'live' => ['bg-cc-gold', 'live'], 'idle' => ['bg-cc-gray', 'flag']]; @endphp
<section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">Mission Route</h2>
            <p class="mt-0.5 text-[11px] text-cc-ink-3">Company Journey</p>
        </div>
        <a href="{{ route('projects.index') }}" class="shrink-0 text-[11px] font-bold text-cc-blue transition hover:text-cc-navy">View Timeline →</a>
    </div>

    <ol class="mt-5 flex items-start gap-1 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($phases as $p)
            @php [$dot, $icon] = $states[$p['state']] ?? $states['idle']; @endphp
            <li class="relative flex min-w-[74px] flex-1 flex-col items-center text-center">
                @unless ($loop->last)
                    <span class="absolute left-1/2 top-[15px] h-0.5 w-full bg-cc-line"></span>
                @endunless
                <span class="cc-hex-flat relative z-10 grid h-8 w-8 place-items-center {{ $dot }} {{ $p['state'] === 'idle' ? 'text-white' : ($p['state'] === 'live' ? 'text-cc-navy' : 'text-white') }}">
                    <x-canvas.icon :name="$icon" :size="14" />
                </span>
                <span class="mt-2 text-[10.5px] font-bold text-cc-navy">{{ $p['label'] }}</span>
                <span class="mt-0.5 text-[10px] text-cc-ink-3">{{ $p['count'] }}</span>
            </li>
        @endforeach
    </ol>

    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-cc-line pt-3">
        @foreach ([['bg-cc-ok', 'On Track'], ['bg-cc-warn', 'Attention'], ['bg-cc-risk', 'At Risk'], ['bg-cc-gray', 'Not Started']] as [$c, $l])
            <span class="flex items-center gap-1.5 text-[10.5px] text-cc-ink-2"><span class="h-2 w-2 rounded-full {{ $c }}"></span>{{ $l }}</span>
        @endforeach
    </div>
</section>
