@props(['event' => null])
<header {{ $attributes->merge(['class' => 'o-topbar']) }}>
    <span class="o-brandmark"><x-orbit.icon name="orbit" :size="19" /></span>
    {{ $slot }}
</header>
