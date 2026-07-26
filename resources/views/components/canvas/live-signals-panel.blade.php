@props(['items' => []])
@php $tones = ['risk' => ['text-cc-risk', 'bg-cc-risk/10'], 'warn' => ['text-cc-warn', 'bg-cc-warn/10'], 'info' => ['text-cc-info', 'bg-cc-info/10']]; @endphp
<section id="signals" class="scroll-mt-4 rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">Live Signals</h2>
            <p class="mt-0.5 text-[11px] text-cc-ink-3">Real-time operational alerts</p>
        </div>
        <a href="{{ route('events.index') }}" class="shrink-0 text-[11px] font-bold text-cc-blue transition hover:text-cc-navy">View All →</a>
    </div>

    <ul class="mt-3 divide-y divide-cc-line">
        @foreach ($items as $s)
            @php [$text, $bg] = $tones[$s['tone']] ?? $tones['info']; @endphp
            <li>
                <a href="{{ $s['href'] ?? '#' }}" class="group flex items-start gap-3 py-3 transition hover:bg-cc-mist/50">
                    <span class="cc-hex-flat mt-0.5 grid h-8 w-8 shrink-0 place-items-center {{ $bg }} {{ $text }}"><x-canvas.icon name="risk" :size="14" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[9.5px] font-extrabold uppercase tracking-[0.12em] {{ $text }}">{{ $s['impact'] }}</span>
                        <span class="mt-1 block truncate text-[12.5px] font-bold text-cc-navy">{{ $s['title'] }}</span>
                        <span class="mt-0.5 block truncate text-[11px] text-cc-ink-3">{{ $s['context'] }}</span>
                    </span>
                    <span class="flex shrink-0 flex-col items-end gap-1.5">
                        <span class="text-[10.5px] tabular-nums text-cc-ink-3">{{ $s['time'] }}</span>
                        <span class="grid h-6 w-6 place-items-center rounded-full bg-cc-mist text-cc-ink-3 transition group-hover:bg-cc-navy group-hover:text-cc-gold"><x-canvas.icon name="chevR" :size="12" /></span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
