@props(['show' => false])

{{--
    A generic, opt-in floating right panel — ships empty/unused in Phase 1.
    A page opts in by passing a $rightPanel slot to x-layouts.app; nothing
    does yet. Event Hub's x-eo.hubx-inspector and Venue Studio's
    x-eo.venue-studio.command-tower stay exactly where they are (page
    content, not chrome) until a later phase migrates them here.
--}}
@if ($show)
    <aside class="w-[300px] shrink-0 rounded-[22px] border border-line bg-white p-4 shadow-[0_20px_50px_-32px_rgba(11,31,58,0.45)]">
        {{ $slot }}
    </aside>
@endif
