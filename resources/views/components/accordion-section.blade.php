{{--
    One panel of an <x-accordion>. Must be rendered inside one — it reads and
    writes the group's `at`, which is what makes opening this one close the
    last one.

    The body animates with x-collapse (Alpine's own, bundled with Livewire), so
    the height is transitioned from the content's real measured height rather
    than a guessed max-height that either clips long sections or leaves short
    ones sliding through empty space. 300ms on the standard material easing:
    long enough to read as movement, short enough that clicking through eleven
    sections never feels like waiting.

    x-cloak keeps a closed panel closed through the first paint — without it
    every section flashes open on load, before Alpine has run.
--}}
@props([
    'id',
    'title',
    'num' => null,
    'summary' => null,
])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-line bg-white transition-shadow duration-300']) }}
         x-bind:class="at === @js($id) && 'shadow-[0_18px_40px_-30px_rgba(11,31,58,0.5)]'">
    <button type="button"
            x-on:click="at = (at === @js($id) ? null : @js($id))"
            x-bind:aria-expanded="at === @js($id) ? 'true' : 'false'"
            class="flex w-full items-center gap-3 px-5 py-3.5 text-left transition-colors hover:bg-page/60">
        @if ($num !== null)
            <span class="pf text-lg font-bold text-gold-700">{{ $num }}</span>
        @endif
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-navy-900">{{ $title }}</span>
            @if ($summary !== null)
                <span class="block truncate text-eyebrow text-muted">{{ $summary }}</span>
            @endif
        </span>
        <span class="shrink-0 text-navy-300 transition-transform duration-300"
              x-bind:class="at === @js($id) && 'rotate-180'" aria-hidden="true">▾</span>
    </button>

    <div x-show="at === @js($id)" x-collapse.duration.300ms x-cloak class="border-t border-line px-5 py-4">
        {{ $slot }}
    </div>
</section>
