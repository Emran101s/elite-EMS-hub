@props([
    'term',
    'used' => 0,
    'usesColor' => false,
    'storesLabel' => false,
    'child' => false,   // a sub-term: indented, and it cannot hold children of its own
])

{{-- One term in one of the editable lists. Shared by the top level and the
     level beneath it so a sub-category reads and behaves the same as its
     parent — only its indent says it sits under something. --}}
<div class="group/row flex items-center gap-3 py-2.5 pe-4 transition hover:bg-page/40
            {{ $child ? 'ps-11' : 'px-4' }} {{ $term->is_active ? '' : 'bg-page/50' }}">

    <span class="cat-drag grid h-6 w-4 shrink-0 cursor-grab place-items-center text-navy-200 transition group-hover/row:text-navy-400"
          title="Drag to reorder">⋮⋮</span>

    @if ($usesColor)
        <span class="h-3.5 w-3.5 shrink-0 rounded-full ring-1 ring-line"
              style="background: {{ $term->color ?: 'var(--color-navy-100)' }}"></span>
    @endif

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            <span class="truncate {{ $child ? 'text-[12px] font-semibold' : 'text-[12.5px] font-bold' }}
                         {{ $term->is_active ? 'text-navy-900' : 'text-navy-400 line-through' }}">{{ $term->label }}</span>
            @unless ($term->is_active)<span class="chip">Hidden</span>@endunless
        </div>
        <div class="flex items-center gap-2 text-[10.5px] text-muted">
            {{-- The key is what records store, so it is shown and never edited.
                 On a list that stores the words themselves it would just repeat
                 the label, so it is left out there. --}}
            @unless ($storesLabel)
                <code class="rounded bg-page px-1 py-px font-mono text-[10px] text-navy-400">{{ $term->key }}</code>
            @endunless
            @if ($term->note)<span class="truncate">{{ $term->note }}</span>@endif
        </div>
    </div>

    {{-- The count is the whole point of the row: it says what you are about to
         hide or remove before you do it. --}}
    <span class="w-12 shrink-0 text-right text-[11px] tabular-nums {{ $used ? 'font-bold text-navy-600' : 'text-navy-200' }}">
        {{ $used ? number_format($used) : '—' }}
    </span>

    <div class="flex shrink-0 items-center gap-1">
        {{-- Adding a sub-term lives here rather than on its own row: a list of
             nine categories does not need nine extra rows to say "add". --}}
        @unless ($child)
            <button type="button" wire:click="newTerm({{ $term->id }})"
                    title="Add a sub-category under {{ $term->label }}"
                    class="grid h-7 w-7 place-items-center rounded-lg text-navy-300 opacity-0 transition hover:bg-gold-50 hover:text-gold-700 focus-visible:opacity-100 group-hover/row:opacity-100">＋</button>
        @else
            <span class="w-7 shrink-0"></span>
        @endunless
        <button type="button" wire:click="toggleActive({{ $term->id }})"
                title="{{ $term->is_active ? 'Stop offering this on new records' : 'Offer this again' }}"
                class="grid h-7 w-7 place-items-center rounded-lg text-[13px] text-navy-300 transition hover:bg-navy-50 hover:text-navy-900">
            {{ $term->is_active ? '◉' : '○' }}
        </button>
        <button type="button" wire:click="edit({{ $term->id }})" class="btn-ghost btn-xs">Edit</button>
        <button type="button" wire:click="delete({{ $term->id }})"
                wire:confirm="{{ match (true) {
                    $used > 0 => number_format($used).' record'.($used === 1 ? '' : 's').' still use “'.$term->label.'”, so it will be hidden rather than deleted and they keep their label. Continue?',
                    $term->is_system => 'The platform names “'.$term->label.'” in its own code, so it will be hidden rather than deleted. Continue?',
                    default => 'Delete “'.$term->label.'”? Nothing is using it.',
                } }}"
                class="grid h-7 w-7 place-items-center rounded-lg text-navy-300 transition hover:bg-risk/10 hover:text-risk">✕</button>
    </div>
</div>
