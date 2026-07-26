@props(['event', 'health' => null, 'open' => false])
@php
    use App\Support\CommandCanvasData as D;

    $score = (int) ($health['score'] ?? 0);
    $band = D::band($score);

    $today = now()->startOfDay();
    $start = $event->starts_at?->copy()->startOfDay();
    $end = ($event->ends_at ?? $event->starts_at)?->copy()->endOfDay();
    $isLive = $start && $end && $start->lte($today) && $end->gte($today);
    $days = $start ? (int) round($today->diffInDays($start, false)) : null;

    $stageTone = match ($event->stage) {
        'live', 'production' => 'bg-cc-info',
        'confirmed', 'completed' => 'bg-cc-ok',
        'cancelled', 'on_hold' => 'bg-cc-risk',
        default => 'bg-cc-plan',
    };

    $tasks = $event->tasks;
    $done = $tasks->where('status', 'done')->count();
    $progress = $tasks->count() ? (int) round($done / $tasks->count() * 100) : 0;
@endphp
{{--
    The Command Card. One card for the whole platform: a navy identity panel
    carrying the crest and live state, a body of facts, and the health hexagon
    as the single instrument. Same scale as the canvas pods, so a score means
    the same thing wherever you meet it.
--}}
<div class="overflow-hidden rounded-[22px] border border-cc-line bg-white transition duration-300 cc-lift-2
            hover:border-cc-gold hover:cc-lift-3 {{ $open ? 'border-cc-gold cc-lift-3' : '' }}">
<button type="button" wire:click="toggleExpand({{ $event->id }})" class="group flex w-full text-left">

    {{-- identity panel --}}
    <span class="relative hidden w-[104px] shrink-0 overflow-hidden bg-gradient-to-b from-cc-navy to-cc-navy-3 sm:block">
        <span class="cc-honey absolute inset-0 opacity-70"></span>
        @if ($event->cover_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($event->cover_path) }}" alt=""
                 class="absolute inset-0 h-full w-full object-cover opacity-45 transition duration-500 group-hover:scale-105">
        @endif
        <span class="relative flex h-full flex-col items-center justify-between p-3 text-center">
            <span class="cc-hex-flat grid h-9 w-8 place-items-center bg-cc-gold text-[10px] font-extrabold text-cc-navy">
                {{ \Illuminate\Support\Str::of($event->name)->explode(' ')->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
            </span>
            @if ($isLive)
                <span class="flex items-center gap-1 rounded-full bg-cc-gold px-2 py-0.5 text-[8.5px] font-extrabold uppercase tracking-wider text-cc-navy">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-cc-navy"></span>Live
                </span>
            @elseif ($days !== null)
                <span class="text-[10px] font-bold text-white/70">{{ $days > 0 ? $days.'d' : 'Done' }}</span>
            @endif
        </span>
    </span>

    {{-- body --}}
    <span class="flex min-w-0 flex-1 flex-col p-4">
        <span class="flex items-start justify-between gap-3">
            <span class="min-w-0">
                <span class="flex items-center gap-1.5">
                    <span class="h-[7px] w-[7px] shrink-0 rounded-full {{ $stageTone }}"></span>
                    <span class="text-[9.5px] font-bold uppercase tracking-[0.13em] text-cc-ink-2">{{ str($event->stage)->replace('_', ' ')->title() }}</span>
                </span>
                <span class="mt-1.5 block truncate text-[15.5px] font-extrabold tracking-tight text-cc-navy">{{ $event->name }}</span>
                <span class="mt-1 block truncate text-[11.5px] text-cc-ink-2">{{ $event->client?->name ?? str($event->type)->replace('_', ' ')->title() }}</span>
            </span>
            <x-canvas.health-badge :score="$score" size="sm" class="shrink-0" />
        </span>

        <span class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-cc-ink-2">
            <span class="flex items-center gap-1"><x-canvas.icon name="cal" :size="12" class="shrink-0 text-cc-ink-3" />{{ $event->starts_at?->format('j M Y') ?? 'No date' }}</span>
            <span class="flex min-w-0 items-center gap-1"><x-canvas.icon name="pin" :size="12" class="shrink-0 text-cc-ink-3" /><span class="truncate">{{ $event->venue?->name ?? $event->city }}</span></span>
        </span>

        <span class="mt-3 grid grid-cols-3 gap-2 border-t border-cc-line pt-3 text-center">
            @foreach ([
                ['people', $event->attendees_count ?? $event->attendees()->count(), 'Participants'],
                ['supplier', $event->suppliers_count ?? $event->suppliers->count(), 'Suppliers'],
                ['tasks', $tasks->count(), 'Tasks'],
            ] as [$icon, $value, $label])
                <span class="block">
                    <span class="flex items-center justify-center gap-1 text-[13px] font-extrabold text-cc-navy">
                        <x-canvas.icon :name="$icon" :size="12" class="text-cc-ink-3" />{{ number_format($value) }}
                    </span>
                    <span class="mt-0.5 block text-[9px] font-bold uppercase tracking-wider text-cc-ink-3">{{ $label }}</span>
                </span>
            @endforeach
        </span>

        <span class="mt-3 flex items-center gap-2">
            <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-cc-line">
                <span class="block h-full rounded-full bg-cc-navy transition-all duration-700" style="width:{{ max($progress, $progress ? 3 : 0) }}%"></span>
            </span>
            <span class="text-[10.5px] font-bold tabular-nums text-cc-ink-2">{{ $progress }}%</span>
            <x-canvas.icon name="chev" :size="14" class="text-cc-ink-3 transition group-hover:text-cc-gold {{ $open ? 'rotate-180' : '' }}" />
        </span>
    </span>
</button>

    {{-- the detail opens in place, so the grid never jumps you elsewhere --}}
    @if ($open)
        {{ $slot }}
    @endif
</div>
