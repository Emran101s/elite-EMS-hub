@props(['items' => []])
@php $tones = ['risk' => 'text-cc-risk bg-cc-risk/10', 'warn' => 'text-cc-warn bg-cc-warn/10', 'info' => 'text-cc-info bg-cc-info/10']; @endphp
<section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
    <div class="flex items-start gap-2.5">
        <span class="cc-hex-flat grid h-8 w-8 shrink-0 place-items-center bg-cc-navy text-cc-gold"><x-canvas.icon name="ai" :size="16" /></span>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <h2 class="truncate text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">AI Executive Director</h2>
                <span class="shrink-0 rounded-md bg-cc-gold px-1.5 py-0.5 text-[8.5px] font-extrabold tracking-wider text-cc-navy">BETA</span>
            </div>
            <p class="mt-0.5 text-[11px] text-cc-ink-3">Your AI Chief of Staff</p>
        </div>
    </div>

    <p class="mt-4 text-[17px] font-extrabold tracking-tight text-cc-navy">Good morning, Emran 👋</p>
    <p class="mt-1 text-[12px] text-cc-ink-2">Here's your recommended route for today.</p>

    <ol class="mt-4 space-y-2">
        @foreach ($items as $i => $item)
            <li>
                <a href="{{ $item['href'] ?? '#' }}" class="group flex items-center gap-3 rounded-xl border border-cc-line p-2.5 transition hover:border-cc-gold hover:bg-cc-mist/60">
                    <span class="cc-hex-flat grid h-8 w-8 shrink-0 place-items-center text-[11px] font-extrabold {{ $tones[$item['tone']] ?? $tones['info'] }}">{{ $i + 1 }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[12.5px] font-bold text-cc-navy">{{ $item['title'] }}</span>
                        <span class="mt-0.5 block text-[11px] text-cc-ink-3">{{ $item['impact'] }} · {{ $item['due'] }}</span>
                    </span>
                    <x-canvas.icon name="chevR" :size="14" class="shrink-0 text-cc-ink-3 transition group-hover:translate-x-0.5 group-hover:text-cc-gold" />
                </a>
            </li>
        @endforeach
    </ol>

    <a href="{{ route('reports.index') }}" class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-cc-navy py-3 text-[12.5px] font-extrabold text-cc-gold transition hover:bg-cc-navy-2">
        View Full Briefing <x-canvas.icon name="chevR" :size="14" />
    </a>
</section>
