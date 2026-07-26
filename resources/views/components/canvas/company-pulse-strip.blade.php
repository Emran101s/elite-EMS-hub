@props(['items' => [], 'health' => ['value' => 0, 'label' => '']])
{{-- The dark 20%. A single navy bar carrying the company's vital signs. --}}
<section class="cc-honey relative overflow-hidden rounded-[22px] bg-gradient-to-r from-cc-navy-2 via-cc-navy to-cc-navy-2 px-2 py-1 cc-lift-3">
    <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-cc-gold/10 blur-2xl"></div>

    {{-- Wraps to a second row rather than truncating labels: a KPI you can't read
         is not a KPI. --}}
    <div class="relative flex items-stretch gap-1 overflow-x-auto [scrollbar-width:none] md:flex-wrap md:overflow-x-visible [&::-webkit-scrollbar]:hidden">
        @foreach ($items as $item)
            <div class="group flex min-w-[186px] flex-1 items-center gap-3 rounded-2xl px-4 py-4 transition hover:bg-white/[0.06]">
                <span class="cc-hex-flat grid h-11 w-11 shrink-0 place-items-center bg-cc-gold/15 text-cc-gold transition group-hover:bg-cc-gold/25">
                    <x-canvas.icon :name="$item['icon']" :size="19" />
                </span>
                <div class="min-w-0">
                    <p class="whitespace-nowrap text-[10px] font-bold uppercase tracking-[0.14em] text-white/55">{{ $item['label'] }}</p>
                    <p class="mt-1 whitespace-nowrap text-[22px] font-extrabold leading-none tracking-tight text-white">{{ $item['value'] }}</p>
                    @if (! empty($item['delta']))
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-1 whitespace-nowrap text-[11px] font-semibold {{ $item['dir'] === 'up' ? 'text-cc-ok' : 'text-cc-risk' }}">
                            {{ $item['dir'] === 'up' ? '↑' : '↓' }} {{ $item['delta'] }}
                            <span class="font-normal text-white/40">vs last month</span>
                        </p>
                    @elseif (! empty($item['badge']))
                        <p class="mt-1.5 flex items-center gap-1.5 text-[11px] font-semibold text-cc-gold">
                            <span class="h-1.5 w-1.5 rounded-full bg-cc-gold"></span>{{ $item['badge'] }}
                        </p>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Overall health, as a gold ring --}}
        <div class="flex min-w-[188px] flex-1 items-center gap-4 rounded-2xl px-5 py-4 lg:border-l lg:border-white/10">
            @php $r = 26; $c = 2 * M_PI * $r; @endphp
            <span class="relative grid h-[62px] w-[62px] shrink-0 place-items-center">
                <svg width="62" height="62" viewBox="0 0 62 62" class="-rotate-90">
                    <circle cx="31" cy="31" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="5" class="text-white/12" />
                    <circle cx="31" cy="31" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"
                            class="text-cc-gold" stroke-dasharray="{{ round($c, 1) }}"
                            stroke-dashoffset="{{ round($c - $c * $health['value'] / 100, 1) }}" />
                </svg>
                <span class="absolute text-[15px] font-extrabold text-white">{{ $health['value'] }}%</span>
            </span>
            <div>
                <p class="text-[12.5px] font-bold text-white">Overall Health</p>
                <p class="mt-1 text-[12px] font-semibold text-cc-gold">{{ $health['label'] }}</p>
            </div>
        </div>
    </div>
</section>
