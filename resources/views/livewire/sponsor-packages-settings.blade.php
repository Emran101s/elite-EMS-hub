<div class="max-w-3xl">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-navy-900">← Settings</a>
            <h1 class="mt-1 text-lg font-bold text-navy-900">Sponsorship Packages</h1>
            <p class="text-xs text-muted">Your standard sponsorship tiers — every new event seeds its packages from this list.</p>
        </div>
        <div class="flex items-center gap-2">
            <x-confirm title="Reset to the standard package list?" confirm="Reset" tone="warn" run="$wire.resetPackages" class="text-eyebrow font-semibold text-navy-400 hover:text-navy-700">↺ Reset to standard</x-confirm>
            <button type="button" wire:click="addPackage" class="btn-gold h-10 px-4 text-xs">＋ Add package</button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="grid gap-4">
        @forelse ($packages as $i => $p)
            <div wire:key="pkg-{{ $i }}" class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-bold text-gold-400">{{ $i + 1 }}</span>
                    <input type="text" wire:model="packages.{{ $i }}.name" placeholder="Tier name — e.g. Platinum Partner" class="input h-10 flex-1 text-sm font-semibold">
                    <button type="button" wire:click="move({{ $i }}, -1)" @disabled($i === 0) class="rounded px-1.5 text-navy-400 hover:text-navy-900 disabled:opacity-20">↑</button>
                    <button type="button" wire:click="move({{ $i }}, 1)" @disabled($i === count($packages) - 1) class="rounded px-1.5 text-navy-400 hover:text-navy-900 disabled:opacity-20">↓</button>
                    <button type="button" wire:click="removePackage({{ $i }})" class="rounded-lg bg-risk/10 px-2 py-1.5 text-micro font-bold text-red-700 hover:bg-risk/20">✕</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-[1fr_1fr_2fr]">
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Price ({{ $currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="packages.{{ $i }}.price" placeholder="0" class="input h-9 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Max slots</label>
                        <input type="number" min="0" wire:model="packages.{{ $i }}.slots" placeholder="∞" class="input h-9 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Benefits — one per line</label>
                        <textarea wire:model="packages.{{ $i }}.benefits" rows="3" class="input text-sm" placeholder="Logo on main stage&#10;10 delegate passes&#10;Speaking slot"></textarea>
                    </div>
                </div>
            </div>
        @empty
            <x-empty icon="star" title="No packages yet"
                     hint="Sponsorship tiers you define here become the default packages every new event can sell.">
                <x-slot:actions>
                    <button type="button" wire:click="addPackage" class="btn-gold btn-sm">＋ Add your first package</button>
                </x-slot:actions>
            </x-empty>
        @endforelse

        @if (! empty($packages))
            <div class="flex justify-end">
                <button type="submit" class="btn-navy h-10 px-6 text-xs">Save packages</button>
            </div>
        @endif
    </form>
</div>
