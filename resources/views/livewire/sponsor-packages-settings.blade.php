<div class="max-w-3xl">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-ink">← Settings</a>
            <h1 class="mt-1 text-lg font-bold text-ink">Sponsorship Packages</h1>
            <p class="text-xs text-muted">Your standard sponsorship tiers — every new event seeds its packages from this list.</p>
        </div>
        <div class="flex items-center gap-2">
            <x-confirm title="Reset to the standard package list?" confirm="Reset" tone="warn" run="$wire.resetPackages" class="text-[11px] font-semibold text-muted hover:text-ink">↺ Reset to standard</x-confirm>
            <button type="button" wire:click="addPackage" class="h-10 rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add package</button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-success/30 bg-success-soft px-4 py-2 text-xs font-semibold text-success-ink">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="grid gap-4">
        @forelse ($packages as $i => $p)
            <div wire:key="pkg-{{ $i }}" class="rounded-lg border border-line bg-white shadow-raise p-5">
                <div class="mb-3 flex items-center gap-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-[11px] font-bold text-gold-600">{{ $i + 1 }}</span>
                    <input type="text" wire:model="packages.{{ $i }}.name" placeholder="Tier name — e.g. Platinum Partner" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 flex-1 text-sm font-semibold">
                    <button type="button" wire:click="move({{ $i }}, -1)" @disabled($i === 0) class="rounded px-1.5 text-muted hover:text-ink disabled:opacity-20">↑</button>
                    <button type="button" wire:click="move({{ $i }}, 1)" @disabled($i === count($packages) - 1) class="rounded px-1.5 text-muted hover:text-ink disabled:opacity-20">↓</button>
                    <button type="button" wire:click="removePackage({{ $i }})" class="rounded-lg bg-danger/10 px-2 py-1.5 text-micro font-bold text-danger-ink hover:bg-danger/20">✕</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-[1fr_1fr_2fr]">
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Price ({{ $currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="packages.{{ $i }}.price" placeholder="0" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 text-sm">
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Max slots</label>
                        <input type="number" min="0" wire:model="packages.{{ $i }}.slots" placeholder="∞" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 text-sm">
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Benefits — one per line</label>
                        <textarea wire:model="packages.{{ $i }}.benefits" rows="3" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none text-sm" placeholder="Logo on main stage&#10;10 delegate passes&#10;Speaking slot"></textarea>
                    </div>
                </div>
            </div>
        @empty
            <x-eo.empty-state icon="star" title="No packages yet"
                     hint="Sponsorship tiers you define here become the default packages every new event can sell.">
                <x-slot:actions>
                    <button type="button" wire:click="addPackage" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add your first package</button>
                </x-slot:actions>
            </x-eo.empty-state>
        @endforelse

        @if (! empty($packages))
            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Save packages</button>
            </div>
        @endif
    </form>
</div>
