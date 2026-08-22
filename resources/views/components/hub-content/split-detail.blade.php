@props(['selected' => null, 'label' => 'Selected', 'title' => null, 'closeAction' => null, 'emptyIcon' => 'clipboard', 'emptyTitle' => 'Nothing selected', 'emptyHint' => 'Select a row from the list to see its full detail and take action.'])

<div class="ehc-detail">
    @if ($selected)
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-[9.5px] font-extrabold uppercase tracking-[0.08em] text-white/45">{{ $label }}</p>
                <p class="mt-1 text-[15px] font-extrabold leading-tight text-white">{{ $title }}</p>
            </div>
            @if ($closeAction)
                <button type="button" wire:click="{{ $closeAction }}" title="Close"
                        class="grid h-[22px] w-[22px] shrink-0 place-items-center rounded-full text-white/50 transition hover:bg-white/10 hover:text-white">
                    <span aria-hidden="true">✕</span>
                </button>
            @endif
        </div>

        {{ $slot }}
    @else
        <div class="flex flex-col items-center gap-1.5 py-7 text-center">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-white/60">
                <x-icon :name="$emptyIcon" class="h-4 w-4" />
            </span>
            <p class="text-[12px] font-semibold text-white/80">{{ $emptyTitle }}</p>
            <p class="text-[11px] text-white/50">{{ $emptyHint }}</p>
        </div>
    @endif
</div>
