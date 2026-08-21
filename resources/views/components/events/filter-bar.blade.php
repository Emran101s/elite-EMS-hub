@props(['typeTabs', 'tab', 'sort', 'starred', 'hasActiveFilters'])

<div class="space-y-4 rounded-lg border border-line bg-white p-4 sm:p-5">
    <div class="flex flex-wrap items-center gap-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Portfolio Command Filter</p>

        <div class="relative ms-auto">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search missions…"
                   class="h-9 w-44 rounded-md border border-line bg-white ps-9 text-xs outline-none focus:border-gold-400 sm:w-52">
        </div>

        <div class="inline-flex items-center gap-0.5 rounded-lg border border-line bg-white p-0.5">
            @foreach (['date' => 'Date', 'health' => 'Health', 'budget' => 'Budget'] as $key => $label)
                <button type="button" wire:click="$set('sort', '{{ $key }}')"
                        @class(['rounded-md px-2.5 py-1.5 text-[11px] font-bold transition',
                                'bg-navy-900 text-white' => $sort === $key,
                                'text-muted hover:text-ink' => $sort !== $key])>{{ $label }}</button>
            @endforeach
        </div>

        <button type="button" wire:click="toggleStarred"
                @class(['inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-2.5 py-1.5 text-[11.5px] font-bold text-ink transition', '!text-gold-700 !border-gold-300' => $starred])>
            <x-icon name="star" class="h-3.5 w-3.5 {{ $starred ? 'fill-current' : '' }}" /> Starred
        </button>

        @if ($hasActiveFilters)
            <a href="{{ route('events.index') }}" wire:navigate
               class="text-[11.5px] font-semibold text-muted transition hover:text-danger-ink">Clear filters</a>
        @endif
    </div>

    <div>
        <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Event type</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($typeTabs as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')"
                        @class([
                            'rounded-full border px-3 py-1.5 text-[11.5px] font-semibold transition',
                            'border-navy-900 bg-navy-900 text-white' => $tab === $key,
                            'border-line bg-white text-muted hover:border-gold-300 hover:text-ink' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>
</div>
