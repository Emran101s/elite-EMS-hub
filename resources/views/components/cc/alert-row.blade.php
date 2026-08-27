@props(['tone' => 'info', 'title'])

@php
    $soft = match ($tone) {
        'risk' => 'bg-danger-soft', 'warn' => 'bg-warning-soft', default => 'bg-info-soft',
    };
    $bar = match ($tone) {
        'risk' => 'bg-danger', 'warn' => 'bg-warning', default => 'bg-info',
    };
    $ink = match ($tone) {
        'risk' => 'text-danger-ink', 'warn' => 'text-warning-ink', default => 'text-info-ink',
    };
@endphp

<div class="relative overflow-hidden rounded-md {{ $soft }} py-2.5 pl-3.5 pr-3">
    <span class="absolute inset-y-0 left-0 w-1 {{ $bar }}" aria-hidden="true"></span>
    <p class="text-[12.5px] font-bold {{ $ink }}">{{ $title }}</p>
    <p class="mt-0.5 text-[11.5px] text-ink/70">{{ $slot }}</p>
</div>
