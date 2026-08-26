@props([
    'target' => null,
    'busy' => 'Working…',
])

{{--
    Idle / busy swap for a button label. Put inside the button that fires the
    Livewire action — keeps "Saving…" language consistent without re-handing
    wire:loading.remove / wire:loading on every screen.

    <button wire:click="save" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
        <x-busy target="save" busy="Saving…">Save</x-busy>
    </button>
--}}
<span @if ($target) wire:loading.remove wire:target="{{ $target }}" @else wire:loading.remove @endif>
    {{ $slot }}
</span>
<span @if ($target) wire:loading wire:target="{{ $target }}" @else wire:loading @endif
      class="inline-flex items-center gap-1.5">
    <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-opacity="0.25" stroke-width="2"/>
        <path d="M14 8a6 6 0 0 0-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    {{ $busy }}
</span>
