@props(['items' => []])
@php $tones = ['risk' => 'bg-cc-risk', 'warn' => 'bg-cc-warn', 'info' => 'bg-cc-blue']; @endphp
<section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">Upcoming Milestones</h2>
            <p class="mt-0.5 text-[11px] text-cc-ink-3">Next 7 Days</p>
        </div>
        <a href="{{ route('tasks.index') }}" class="shrink-0 text-[11px] font-bold text-cc-blue transition hover:text-cc-navy">View All →</a>
    </div>

    <ul class="mt-4 divide-y divide-cc-line">
        @foreach ($items as $m)
            <li class="flex items-center gap-3 py-2.5">
                <span class="h-2 w-2 shrink-0 rounded-full {{ $tones[$m['tone']] ?? $tones['info'] }}"></span>
                <span class="min-w-0 flex-1 truncate text-[12.5px] font-semibold text-cc-navy">{{ $m['label'] }}</span>
                <span class="shrink-0 text-[11px] font-bold text-cc-ink-3">{{ $m['when'] }}</span>
            </li>
        @endforeach
    </ul>
</section>
