@props(['title', 'subtitle' => null])

<div class="rounded-lg border border-line bg-white p-4">
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <p class="text-[14px] font-bold text-ink">{{ $title }}</p>
            @if ($subtitle)
                <p class="mt-0.5 text-[11.5px] text-muted">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($header){{ $header }}@endisset
    </div>

    {{ $slot }}

    @isset($footer)
        <div class="mt-4 border-t border-line pt-3.5">{{ $footer }}</div>
    @endisset
</div>
