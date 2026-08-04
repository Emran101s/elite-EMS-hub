@props(['mission', 'size' => 'sm'])

{{-- The status pill. One vocabulary of five, one set of colours, every view.
     Tone keys (slate/orange/…) come from EventMission::STATUSES; the classes
     below map those keys onto the live navy/gold/semantic palette so the
     badge stops counting as design-token drift. --}}

@php
    $tone = [
        'slate' => 'bg-navy-50 text-navy-600 ring-navy-100',
        'orange' => 'bg-amber-100 text-amber-700 ring-amber-200/70',
        'indigo' => 'bg-gold-100 text-gold-800 ring-gold-200/70',
        'blue' => 'bg-info-soft text-info-ink ring-info/20',
        'green' => 'bg-emerald-100 text-emerald-700 ring-emerald-200/70',
    ][$mission['statusTone']] ?? 'bg-navy-50 text-navy-600 ring-navy-100';

    $scale = $size === 'xs' ? 'px-1.5 py-0.5 text-[8.5px]' : 'px-2.5 py-1 text-[9.5px]';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full font-black uppercase tracking-[0.13em] ring-1 $tone $scale"]) }}>
    @if ($mission['live'])
        <span class="relative flex h-1.5 w-1.5">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-current opacity-60"></span>
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-current"></span>
        </span>
    @endif
    {{ $mission['statusLabel'] }}
</span>
