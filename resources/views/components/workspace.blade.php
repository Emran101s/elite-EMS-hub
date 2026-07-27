@props(['label' => null, 'meta' => null, 'live' => false, 'flush' => false])

{{--
    THE WORKSPACE — the one surface every module works on.

    Lifted from the contract's live preview, which is where the idea proved
    itself: a softly tinted tray with big corners and a hairline ring, holding
    white content. It gives the platform three clear depths instead of two —
    page ground, workspace, then the white cards and papers that live on it —
    so a card no longer has to fight the page to look like an object.

    Same radius, same tint, same ring on every screen. Change it here and the
    whole platform changes with it.

    label/meta render the small caption row the contract preview has
    ("● LIVE PREVIEW" on the left, "English · العربية" on the right).
    flush drops the padding for content that brings its own.
--}}
<div {{ $attributes->class([
    'rounded-3xl bg-navy-900/[0.05] ring-1 ring-line',
    'p-3 sm:p-5' => ! $flush,
]) }}>

    @if ($label || $meta)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 px-1">
            <span class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-500">
                @if ($live)
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                @endif
                {{ $label }}
            </span>
            @if ($meta)
                <span class="text-eyebrow text-muted">{{ $meta }}</span>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
