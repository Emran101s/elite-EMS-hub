@props(['routing'])

{{--
    Where this module's costs land in the budget.

    Rendered in a module's Control Center, next to what that module costs —
    because the question "which section does this show up under" occurs to
    somebody while they are looking at the money, not while they are in the
    budget wondering where a line came from.
--}}
<div class="mt-3 border-t border-line pt-3">
    <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">Costs go to</p>

    <details class="relative mt-1.5" data-menu>
        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl border border-line bg-white px-3 py-2 text-[11.5px] font-semibold text-navy-800 transition hover:border-gold-300 [&::-webkit-details-marker]:hidden">
            <span class="min-w-0 flex-1 truncate">{{ $routing['current'] }}</span>
            <span class="shrink-0 text-navy-300">▾</span>
        </summary>

        <div class="absolute z-30 mt-1 max-h-64 w-64 overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-xl">
            @foreach ($routing['categories'] as $name)
                <button type="button" wire:click="routeCostsTo(@js($name))" wire:key="cat-{{ $loop->index }}"
                        @class([
                            'flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-start text-[12px] font-semibold transition',
                            'bg-navy-950 text-white' => $name === $routing['current'],
                            'text-navy-700 hover:bg-page' => $name !== $routing['current'],
                        ])>
                    <span class="min-w-0 flex-1 truncate">{{ $name }}</span>
                    @if ($name === $routing['current'])<span class="shrink-0 text-gold-400">✓</span>@endif
                </button>
            @endforeach
        </div>
    </details>

    <p class="mt-1.5 text-[10.5px] leading-snug text-muted">
        {{ $routing['label'] }} costs appear under this section of the budget. Changing it re-files them now.
    </p>
</div>
