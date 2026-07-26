@props(['user' => 'Emran Altan', 'role' => 'Super Admin', 'greeting' => 'Good morning, Emran'])
{{-- Header / Command Bar — light, spacious, the only place two buttons compete. --}}
<header class="flex flex-wrap items-center gap-4 bg-white px-4 py-3 2xl:flex-nowrap 2xl:gap-6 2xl:px-6">

    {{-- Brand block: the one navy square, anchoring the whole screen --}}
    <a href="#" class="flex shrink-0 items-center gap-3 rounded-2xl bg-cc-navy px-4 py-3 cc-lift-2">
        <span class="grid h-9 w-9 place-items-center rounded-lg bg-cc-gold">
            <span class="block h-4 w-4 rotate-45 rounded-[3px] border-2 border-cc-navy"></span>
        </span>
        <span class="leading-none">
            <span class="block text-[15px] font-extrabold tracking-[0.22em] text-white">ELITE</span>
            <span class="mt-1 block text-[8px] font-semibold tracking-[0.3em] text-cc-gold">BUSINESS HUB</span>
        </span>
    </a>

    {{-- Greeting --}}
    <div class="min-w-0 basis-full xl:basis-auto xl:flex-1 2xl:flex-none">
        <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-cc-ink-3">Command Canvas</p>
        <h1 class="mt-1 truncate text-xl font-extrabold tracking-tight text-cc-navy xl:text-[26px]">{{ $greeting }} <span class="ml-0.5">👋</span></h1>
        <p class="mt-0.5 truncate text-[12.5px] text-cc-ink-2">You are in command. Everything is connected.</p>
    </div>

    {{-- Global search --}}
    <label class="order-last flex h-12 w-full items-center gap-3 rounded-xl border border-cc-line bg-cc-mist px-4 transition focus-within:border-cc-blue focus-within:bg-white focus-within:ring-4 focus-within:ring-cc-blue/10 2xl:order-none 2xl:max-w-[400px] 2xl:flex-1">
        <x-canvas.icon name="search" :size="17" class="shrink-0 text-cc-ink-3" />
        <input data-command-search type="search" placeholder="Search events, people, venues, tasks, documents..."
               class="min-w-0 flex-1 bg-transparent text-[13.5px] text-cc-ink placeholder:text-cc-ink-3 focus:outline-none">
        <kbd class="shrink-0 rounded-md border border-cc-line bg-white px-1.5 py-0.5 text-[10px] font-bold text-cc-ink-3">⌘ K</kbd>
    </label>

    {{-- Actions --}}
    <div class="ml-auto flex flex-wrap items-center justify-end gap-1.5">
        <button type="button" class="flex h-11 items-center gap-2 rounded-xl bg-cc-navy px-4 text-[13px] font-bold text-white transition hover:bg-cc-navy-2 cc-lift-2">
            <x-canvas.icon name="ai" :size="16" class="text-cc-gold" /> AI Director
        </button>
        <button type="button" class="flex h-11 items-center gap-2 rounded-xl bg-cc-gold px-4 text-[13px] font-bold text-cc-navy transition hover:bg-cc-gold-2 cc-lift-2">
            <x-canvas.icon name="plus" :size="16" /> Create <x-canvas.icon name="chev" :size="14" />
        </button>

        @foreach ([['bell', 6], ['chat', 3], ['cal', null]] as [$icon, $count])
            <button type="button" class="relative grid h-11 w-11 place-items-center rounded-xl border border-cc-line bg-white text-cc-ink-2 transition hover:border-cc-gold hover:text-cc-navy">
                <x-canvas.icon :name="$icon" :size="18" />
                @if ($count)
                    <span class="absolute -right-1 -top-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-cc-risk px-1 text-[10px] font-bold text-white ring-2 ring-white">{{ $count }}</span>
                @endif
            </button>
        @endforeach

        <button type="button" class="flex items-center gap-2.5 rounded-xl border border-cc-line bg-white py-1.5 pl-1.5 pr-3 transition hover:border-cc-gold">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-cc-navy text-[12px] font-bold text-cc-gold">EA</span>
            <span class="hidden text-left leading-tight lg:block">
                <span class="block text-[12.5px] font-bold text-cc-navy">{{ $user }}</span>
                <span class="block text-[10.5px] text-cc-ink-3">{{ $role }}</span>
            </span>
            <x-canvas.icon name="chev" :size="14" class="text-cc-ink-3" />
        </button>
    </div>
</header>
