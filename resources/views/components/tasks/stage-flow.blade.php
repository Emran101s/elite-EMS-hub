@props(['stages'])

{{-- A connected pipeline, not a grid of disconnected tiles — one thin line
     running behind every stage's own dot, so the six numbers read as one
     workflow narrowing toward Done rather than six interchangeable boxes. --}}
<div class="rounded-lg border border-line bg-white p-5">
    <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Workflow</p>

    <div class="relative mt-4">
        <div class="pointer-events-none absolute inset-x-[8%] top-[7px] h-px bg-line" aria-hidden="true"></div>

        <div class="relative grid grid-cols-3 gap-y-5 sm:grid-cols-6 sm:gap-y-0">
            @foreach ($stages as $key => $s)
                <a href="{{ route('tasks.index', ['status' => $key]) }}"
                   class="group flex flex-col items-center gap-2 px-1 text-center transition hover:-translate-y-0.5">
                    <span class="relative z-10 h-3.5 w-3.5 shrink-0 rounded-full ring-[5px] ring-white transition group-hover:ring-4"
                          style="background: {{ $s['hex'] }}"></span>
                    <span class="text-[22px] font-extrabold tabular-nums leading-none text-ink">{{ $s['count'] }}</span>
                    <span class="text-[10px] font-bold uppercase tracking-[0.06em] text-muted">{{ $s['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
