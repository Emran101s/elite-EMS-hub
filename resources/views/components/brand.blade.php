@props(['dark' => false])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg class="h-9 w-9 shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect x="20" y="3.5" width="23.3" height="23.3" rx="4" transform="rotate(45 20 3.5)" stroke="#D4AF37" stroke-width="2.5"/>
        <rect x="20" y="12.5" width="10.6" height="10.6" rx="2" transform="rotate(45 20 12.5)" fill="#D4AF37"/>
    </svg>
    <span class="flex flex-col leading-none">
        <span class="text-xl font-bold tracking-[0.28em] {{ $dark ? 'text-white' : 'text-navy-900' }}">ELITE</span>
        <span class="mt-1 text-[0.55rem] font-semibold tracking-[0.34em] {{ $dark ? 'text-gold-300' : 'text-gold-600' }}">BUSINESS&nbsp;HUB</span>
    </span>
</span>
