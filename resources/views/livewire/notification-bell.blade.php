<div class="relative" x-data @click.outside="$wire.open && $wire.toggle()" @keydown.escape.window="$wire.open && $wire.toggle()">
    <button type="button" wire:click="toggle" :aria-expanded="$wire.open" title="Notifications"
            class="relative grid h-10 w-10 place-items-center rounded-full bg-white text-navy-600 shadow-[0_2px_10px_-4px_rgba(11,31,58,0.25)] transition hover:text-navy-900">
        <x-icon name="bell" class="h-[18px] w-[18px]" />
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-risk px-1 text-[9px] font-bold text-white ring-2 ring-page">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div x-cloak class="absolute right-0 z-40 mt-2 w-80 overflow-hidden rounded-2xl border border-line bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
                <span class="text-[13px] font-bold text-navy-900">Notifications</span>
                <a href="{{ route('home') }}#live-alerts" wire:navigate class="text-[11px] font-semibold text-info hover:underline">Portfolio signals →</a>
            </div>

            <div class="max-h-96 overflow-y-auto">
                @forelse ($notifications as $n)
                    @php $wasUnread = in_array($n->id, $unreadIdsAtOpen, true); @endphp
                    <a href="{{ $n->data['url'] ?? '#' }}"
                       class="flex items-start gap-2.5 border-b border-line px-4 py-3 text-left transition last:border-0 hover:bg-navy-50 {{ $wasUnread ? 'bg-gold-50/40' : '' }}">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $wasUnread ? 'bg-gold-500' : 'bg-transparent' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[12.5px] font-semibold text-navy-900">{{ $n->data['title'] ?? 'Notification' }}</span>
                            <span class="block text-[11.5px] text-muted">{{ $n->data['body'] ?? '' }}</span>
                            <span class="mt-0.5 block text-[10px] text-navy-300">{{ $n->created_at->diffForHumans() }}</span>
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-8 text-center text-[12.5px] text-muted">Nothing yet — you're caught up.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
