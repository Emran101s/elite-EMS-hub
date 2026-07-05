@props(['user', 'size' => 'h-9 w-9', 'text' => 'text-sm'])

{{-- Photo when the user has one; navy/gold initials otherwise. --}}
@if ($user?->avatar_path)
    <img src="{{ asset($user->avatar_path) }}" alt="{{ $user->name }}"
         {{ $attributes->merge(['class' => "{$size} shrink-0 rounded-full object-cover ring-2 ring-line"]) }}>
@else
    <span {{ $attributes->merge(['class' => "flex {$size} shrink-0 items-center justify-center rounded-full bg-navy-900 {$text} font-bold text-gold-400 ring-2 ring-line"]) }}>
        {{ $user?->initials() }}
    </span>
@endif
