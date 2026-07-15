@php
    $depth = $depth ?? 0;
    $item = $node['item'];
    $geo = $node['geo'];
    $done = $item->status === 'done';
    $isTop = $depth === 0;
    $owners = $item->owners;
    $ini = fn ($name) => \Illuminate\Support\Str::of($name)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    [$statusLabel, $statusHex] = \App\Models\EventPlanItem::STATUS_BAR[$item->status] ?? \App\Models\EventPlanItem::STATUS_BAR['todo'];
    $overdue = $item->due_on && ! $done && $item->due_on->isPast();
@endphp

<div class="avoid flex items-stretch border-b border-line/50 {{ $depth > 0 ? 'bg-page/30' : '' }}">
    {{-- Task --}}
    <div class="flex w-[320px] shrink-0 items-center gap-2 py-2 pr-2" style="padding-left: {{ 14 + $depth * 18 }}px">
        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $statusHex }}"></span>
        <span class="flex-1 truncate {{ $isTop ? 'text-[0.86rem] font-bold text-navy-900' : ($depth === 1 ? 'text-[0.79rem] font-semibold text-navy-700' : 'text-[0.74rem] text-navy-600') }} {{ $done ? '!font-normal !text-navy-300 line-through' : '' }}">{{ $item->title }}</span>
        @if ($node['childTotal'])
            <span class="shrink-0 rounded-full bg-navy-50 px-1.5 py-0.5 text-[0.5rem] font-bold text-navy-500">{{ $node['childDone'] }}/{{ $node['childTotal'] }}</span>
        @endif
        @if ($owners->isNotEmpty())
            <span class="flex shrink-0 -space-x-1.5">
                @foreach ($owners->take(3) as $o)
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-navy-800 to-navy-950 text-[0.45rem] font-bold text-gold-300 ring-2 ring-white">{{ $ini($o->name) }}</span>
                @endforeach
                @if ($owners->count() > 3)
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-navy-100 text-[0.45rem] font-bold text-navy-600 ring-2 ring-white">+{{ $owners->count() - 3 }}</span>
                @endif
            </span>
        @endif
    </div>

    {{-- Timeline --}}
    <div class="relative min-w-0 flex-1 {{ $isTop ? 'min-h-[36px]' : 'min-h-[30px]' }}">
        @foreach ($roadmap['ticks'] as $tick)
            <span class="absolute inset-y-0 w-px bg-line/30" style="left: {{ $tick['left'] }}%"></span>
        @endforeach
        @if ($roadmap['todayIn'])<span class="absolute inset-y-0 z-[1] w-px bg-navy-900/35" style="left: {{ $roadmap['todayLeft'] }}%"></span>@endif
        @if ($roadmap['eventLeft'] !== null)<span class="absolute inset-y-0 z-[1] w-0.5 bg-gold-500/60" style="left: {{ $roadmap['eventLeft'] }}%"></span>@endif

        <span class="absolute inset-x-2 top-1/2 h-2.5 -translate-y-1/2 rounded-full bg-navy-50/80"></span>

        @if ($geo)
            @if ($geo['ms'])
                <span class="absolute top-1/2 z-[2] block {{ $isTop ? 'h-3.5 w-3.5' : 'h-3 w-3' }} -translate-y-1/2 -translate-x-1/2 rotate-45 rounded-[3px] ring-2 ring-white"
                      style="left: {{ $geo['left'] }}%; background: {{ $geo['hex'] }}"></span>
            @else
                <span class="absolute top-1/2 z-[2] block -translate-y-1/2 rounded-full {{ $isTop ? 'h-[13px]' : 'h-[10px]' }}"
                      style="left: {{ $geo['left'] }}%; width: {{ $geo['width'] }}%; min-width: 18px; background: {{ $geo['hex'] }}"></span>
            @endif
        @endif
    </div>

    {{-- Start · Due · Status --}}
    <div class="flex w-[66px] shrink-0 items-center justify-center text-[0.64rem] font-medium text-navy-500">{{ $item->starts_on?->format('j M') ?? '—' }}</div>
    <div class="flex w-[66px] shrink-0 items-center justify-center text-[0.64rem] font-medium {{ $overdue ? 'font-bold text-risk' : 'text-navy-500' }}">{{ $item->due_on?->format('j M') ?? '—' }}</div>
    <div class="flex w-[92px] shrink-0 items-center justify-center px-1.5">
        <span class="rounded-full px-2 py-0.5 text-[0.5rem] font-bold uppercase tracking-wide text-white" style="background: {{ $statusHex }}">{{ $statusLabel }}</span>
    </div>
</div>

{{-- children — always expanded in print --}}
@foreach ($node['children'] as $child)
    @include('events.partials.plan-pdf-row', ['node' => $child, 'depth' => $depth + 1, 'roadmap' => $roadmap])
@endforeach
