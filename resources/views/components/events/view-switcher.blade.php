@props(['views', 'view', 'total', 'statuses'])

<div class="flex flex-wrap items-center gap-3">
    <div role="group" aria-label="Choose a view" class="inline-flex items-center gap-1 rounded-xl border border-line bg-white p-1 shadow-raise">
        @foreach ($views as $key => [$label, $icon])
            <button type="button" wire:click="setView('{{ $key }}')"
                    aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                    @class([
                        'relative flex items-center gap-2 rounded-lg px-3.5 py-2 text-[12.5px] font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-1',
                        'bg-navy-900 text-white shadow-float' => $view === $key,
                        'text-muted hover:bg-page hover:text-ink' => $view !== $key,
                    ])>
                <x-icon :name="$icon" class="h-3.5 w-3.5" aria-hidden="true" />{{ $label }}
                @if ($key === 'calendar')
                    <span @class(['rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide',
                        'bg-white/20 text-white' => $view === $key,
                        'bg-page text-muted' => $view !== $key])>Soon</span>
                @endif
            </button>
        @endforeach
    </div>

    <p class="flex items-center gap-1.5 text-[11.5px] font-semibold text-muted">
        <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span>
        {{ $total }} {{ str('mission')->plural($total) }} in view
    </p>

    <div class="ms-auto flex flex-wrap items-center gap-x-3.5 gap-y-1">
        @foreach ($statuses as $key => [$label, $tone, $hex])
            <span class="flex items-center gap-1.5 text-[10.5px] font-semibold text-muted">
                <span class="h-2 w-2 rounded-full ring-2 ring-white" style="background: {{ $hex }}; box-shadow: 0 0 0 1px {{ $hex }}33"></span>{{ $label }}
            </span>
        @endforeach
    </div>
</div>
