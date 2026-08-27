@props([
    'variant' => 'primary', // primary | secondary | ghost | danger | navy
    'size' => null, // sm | null
    'pill' => false,
    'type' => 'button',
    'href' => null,
])

@php
    // Emits brand (navy/gold) utility classes directly rather than the old
    // eo-btn-* teal classes, so every x-eo.button across the app reads on the
    // one navy/gold system. 'primary' is the gold action; 'navy' the solid
    // dark; the rest bordered/soft. Callers' own merged classes still win.
    $base = 'inline-flex items-center justify-center gap-2 rounded-full font-bold transition disabled:cursor-not-allowed disabled:opacity-50';
    $sizeClass = $size === 'sm' ? 'h-9 px-3.5 text-xs' : 'h-10 px-4 text-sm';
    $variantClass = match ($variant) {
        'secondary' => 'border border-line bg-white text-ink hover:border-navy-300',
        'ghost' => 'border border-line bg-white text-ink hover:border-gold-300 hover:text-navy-900',
        'danger' => 'bg-danger-soft text-danger-ink hover:brightness-95',
        'navy' => 'bg-navy-900 text-white shadow-raise hover:bg-navy-800',
        default => 'bg-gold-500 text-navy-900 shadow-raise hover:bg-gold-400',
    };
    $classes = $base.' '.$sizeClass.' '.$variantClass;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </button>
@endif
