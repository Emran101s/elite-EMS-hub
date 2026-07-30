{{--
    A group of collapsible panels where exactly one is open at a time.

    The group owns the state — a single `at` holding the key of whatever is
    open — because that is the only way "opening one closes the others" can be
    true. Panels that each own a boolean cannot know about each other, which is
    how the editors ended up with six sections open at once and a right-hand
    preview you had to hunt through.

    Pass `open` to have one panel open on arrival. Everything else comes from
    <x-accordion-section>, which reads `at` out of this scope.

        <x-accordion open="overview">
            <x-accordion-section id="overview" num="01" title="Event Overview" summary="Written">
                …
            </x-accordion-section>
        </x-accordion>
--}}
@props(['open' => null])

<div x-data="{ at: @js($open) }" {{ $attributes->merge(['class' => 'space-y-3']) }}>
    {{ $slot }}
</div>
