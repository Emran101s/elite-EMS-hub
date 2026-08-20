<div class="max-w-2xl">
    <div class="mb-5">
        <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-eo-muted hover:text-eo-text">← Settings</a>
        <h1 class="mt-1 text-lg font-bold text-eo-text">Defaults &amp; Templates</h1>
        <p class="text-xs text-eo-muted">The starting point every new event inherits — edit once, applied everywhere.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-eo-ok/30 bg-eo-ok-soft px-4 py-2 text-xs font-semibold text-eo-ok-ink">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="grid gap-5">
        {{-- default budget categories --}}
        <div class="eo-soft-card p-5">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-eo-text">Default budget categories</p>
                    <p class="text-micro text-eo-muted">Seeded into every new event's budget, in this order.</p>
                </div>
                <button type="button" wire:click="resetCategories" class="text-[11px] font-semibold text-eo-muted hover:text-eo-text">↺ Reset to standard</button>
            </div>

            <div class="space-y-1.5">
                @forelse ($categories as $i => $cat)
                    <div wire:key="cat-{{ $i }}" class="group flex items-center gap-2 rounded-xl border border-eo-line bg-eo-workspace px-3 py-2">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-eo-navy text-[11px] font-bold text-eo-gold">{{ $i + 1 }}</span>
                        <span class="flex-1 truncate text-sm font-semibold text-eo-text">{{ $cat }}</span>
                        <div class="flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                            <button type="button" wire:click="move({{ $i }}, -1)" @disabled($i === 0) class="rounded px-1.5 text-eo-muted hover:text-eo-text disabled:opacity-20">↑</button>
                            <button type="button" wire:click="move({{ $i }}, 1)" @disabled($i === count($categories) - 1) class="rounded px-1.5 text-eo-muted hover:text-eo-text disabled:opacity-20">↓</button>
                            <button type="button" wire:click="removeCategory({{ $i }})" class="rounded px-1.5 text-micro font-bold text-eo-risk hover:text-eo-risk">✕</button>
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-center text-xs text-eo-muted">No categories — add one below or reset to standard.</p>
                @endforelse
            </div>

            <div class="mt-3 flex items-center gap-2">
                <input type="text" wire:model="newCategory" wire:keydown.enter.prevent="addCategory" placeholder="New category name…" class="eo-input h-9 flex-1 text-sm">
                <button type="button" wire:click="addCategory" class="eo-btn-ghost eo-btn-sm">＋ Add</button>
            </div>
            @error('newCategory')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
        </div>

        {{-- default ticket types --}}
        <div class="eo-soft-card p-5">
            <p class="text-sm font-bold text-eo-text">Ticket / registration types</p>
            <p class="mb-3 text-micro text-eo-muted">The registration types available when adding attendees.</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($tickets as $i => $t)
                    <span wire:key="tk-{{ $i }}" class="group flex items-center gap-1.5 rounded-full border border-eo-line bg-eo-workspace py-1.5 pl-3 pr-1.5 text-xs font-semibold text-eo-text">
                        {{ $t }}
                        <button type="button" wire:click="removeTicket({{ $i }})" class="flex h-4 w-4 items-center justify-center rounded-full text-micro text-eo-muted hover:bg-eo-risk/10 hover:text-eo-risk">✕</button>
                    </span>
                @endforeach
            </div>
            <div class="mt-3 flex items-center gap-2">
                <input type="text" wire:model="newTicket" wire:keydown.enter.prevent="addTicket" placeholder="New ticket type…" class="eo-input h-9 w-56 text-sm">
                <button type="button" wire:click="addTicket" class="eo-btn-ghost eo-btn-sm">＋ Add</button>
            </div>
            @error('newTicket')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
        </div>

        {{-- default management fee --}}
        <div class="eo-soft-card p-5">
            <p class="text-sm font-bold text-eo-text">Default management fee</p>
            <p class="mb-3 text-micro text-eo-muted">Applied on top of each new event's budget subtotal.</p>
            <div class="flex items-center gap-2">
                <input type="number" step="0.5" min="0" max="100" wire:model="fee" class="eo-input h-10 w-28 text-sm">
                <span class="text-sm font-semibold text-eo-muted">%</span>
            </div>
            @error('fee')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
        </div>

        {{-- approval conditional-routing threshold --}}
        <div class="eo-soft-card p-5">
            <p class="text-sm font-bold text-eo-text">Approval escalation threshold</p>
            <p class="mb-3 text-micro text-eo-muted">A budget or payment approval over this amount automatically gets a required Admin sign-off step, on top of a manager's. Leave blank to turn this off.</p>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-eo-muted">{{ \App\Models\CompanyProfile::currency() }}</span>
                <input type="number" step="0.01" min="0" wire:model="approvalThreshold" class="eo-input h-10 w-40 text-sm" placeholder="No threshold">
            </div>
            @error('approvalThreshold')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-end">
            <x-eo.button type="submit" size="sm">Save defaults</x-eo.button>
        </div>
    </form>
</div>
