<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-navy-900">← Settings</a>
            <h1 class="mt-1 text-lg font-bold text-navy-900">Event Avatars</h1>
            <p class="text-xs text-muted">Your library of event cover visuals — upload your own, and each event picks one as its identity across the platform.</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search avatars…" class="input h-10 w-52 text-sm">
            <button type="button" wire:click="newItem" class="btn-gold h-10 px-4 text-xs">＋ Upload avatar</button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($avatars->isEmpty())
        <div class="card px-6 py-16 text-center">
            <p class="text-sm font-semibold text-navy-900">No avatars yet</p>
            <p class="mx-auto mt-1 max-w-md text-xs text-muted">Upload your first event cover visual — it'll be available for every event to use.</p>
            <button type="button" wire:click="newItem" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Upload your first avatar</button>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
            @foreach ($avatars as $a)
                <div wire:key="av-{{ $a->id }}" class="card group flex flex-col overflow-hidden p-0 {{ $a->is_active ? '' : 'opacity-60' }}">
                    <div class="relative flex cursor-pointer items-center justify-center bg-page/60 p-4" wire:click="edit({{ $a->id }})" title="Edit {{ $a->name }}">
                        <x-event-avatar :avatar="$a" :ring="false" size="xl" />
                        @unless ($a->is_active)
                            <span class="absolute left-2 top-2 rounded-full bg-navy-900/80 px-2 py-0.5 text-[0.55rem] font-bold uppercase tracking-wide text-white">Inactive</span>
                        @endunless
                    </div>
                    <div class="flex flex-1 flex-col gap-2 border-t border-line p-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-navy-900">{{ $a->name }}</p>
                            <p class="truncate text-[0.68rem] text-muted">{{ $a->subtitle ?: str($a->category)->title() }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="rounded-full bg-navy-50 px-2 py-0.5 text-[0.55rem] font-bold uppercase tracking-wide text-navy-600">{{ $a->category }}</span>
                            @if ($a->events_count)
                                <span class="rounded-full bg-gold-50 px-2 py-0.5 text-[0.55rem] font-bold text-gold-700 ring-1 ring-gold-200" title="Used by {{ $a->events_count }} event(s)">◆ {{ $a->events_count }} in use</span>
                            @endif
                        </div>
                        <div class="mt-auto flex items-center justify-between pt-1">
                            <button type="button" wire:click="toggleActive({{ $a->id }})"
                                    class="text-[0.62rem] font-semibold {{ $a->is_active ? 'text-emerald-600 hover:text-emerald-700' : 'text-navy-400 hover:text-navy-700' }}">
                                {{ $a->is_active ? '● Active' : '○ Inactive' }}
                            </button>
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="edit({{ $a->id }})" class="rounded-lg bg-navy-50 px-2 py-1 text-[0.6rem] font-bold text-navy-600 hover:bg-navy-100">✎ Edit</button>
                                <button type="button" wire:click="delete({{ $a->id }})"
                                        wire:confirm="Delete “{{ $a->name }}”?{{ $a->events_count ? ' '.$a->events_count.' event(s) use it — they will fall back to the default cover.' : '' }}"
                                        class="rounded-lg bg-risk/10 px-2 py-1 text-[0.6rem] font-bold text-red-700 hover:bg-risk/20">✕</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-center text-[0.62rem] text-muted">{{ $avatars->count() }} {{ str('avatar')->plural($avatars->count()) }} · {{ $avatars->where('is_active', true)->count() }} active</p>
    @endif

    {{-- modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-900/40 p-4 pt-16 backdrop-blur-sm">
            <div class="card w-full max-w-lg p-6 shadow-2xl" @click.outside="$wire.set('showForm', false)">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="pf text-base font-bold text-navy-900">{{ $editingId ? 'Edit avatar' : 'Upload avatar' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="text-navy-400 hover:text-navy-900">✕</button>
                </div>

                <form wire:submit="save" class="grid gap-4">
                    {{-- image --}}
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Cover image {{ $editingId ? '(leave blank to keep current)' : '' }}</label>
                        <div class="flex items-center gap-3">
                            <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-page ring-1 ring-line">
                                @if ($image)
                                    <img src="{{ $image->temporaryUrl() }}" class="h-full w-full object-contain" alt="preview">
                                @elseif ($editingId)
                                    <x-event-avatar :avatar="\App\Models\EventAvatar::find($editingId)" :ring="false" size="lg" />
                                @else
                                    <span class="text-[0.6rem] text-muted">No image</span>
                                @endif
                            </div>
                            <div class="flex-1">
                                <input type="file" wire:model="image" accept="image/png,image/jpeg,image/webp"
                                       class="block w-full text-xs text-navy-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-800">
                                <p class="mt-1 text-[0.6rem] text-muted">PNG, JPG or WebP · up to 4 MB. Transparent PNG looks best.</p>
                                <div wire:loading wire:target="image" class="mt-1 text-[0.6rem] font-semibold text-gold-700">Uploading…</div>
                                @error('image')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Name</label>
                            <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="e.g. Riyadh Summit Stage">
                            @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Category</label>
                            <select wire:model="category" class="input h-10 text-sm">
                                @foreach ($categories as $c)
                                    <option value="{{ $c }}">{{ str($c)->title() }}</option>
                                @endforeach
                            </select>
                            @error('category')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Subtitle (optional)</label>
                        <input type="text" wire:model="subtitle" class="input h-10 text-sm" placeholder="e.g. Luxury Ballroom">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Best for (optional)</label>
                        <input type="text" wire:model="best_for" class="input h-10 text-sm" placeholder="e.g. Conferences, Summits, Forums">
                    </div>

                    <label class="flex items-center gap-2 text-xs font-semibold text-navy-700">
                        <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-line text-gold-500 focus:ring-gold-400">
                        Active — available for events to choose
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,image" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update' : 'Add avatar' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
