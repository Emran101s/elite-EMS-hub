{{-- ══════════ SCOPE OF WORK ══════════

     What the client has asked us to deliver, written here and nowhere else.
     The Event Brief renders this rather than holding a copy — a scope typed
     into two places disagrees with itself the first time one is revised.

     Reads as a document: the work grouped by type, then the exclusions
     gathered at the end where a scope of work puts them.

     Row actions are deliberately NOT hover-gated, unlike the rest of this
     app's list rows (Team, Suppliers, Venues all hide edit/delete until
     :hover). That convention is invisible on a touch device and was the
     literal complaint that produced this rewrite — "I want to add, edit,
     delete" is a discoverability problem, not a missing feature, and the fix
     is to leave the controls on screen. --}}
<div class="cx-canvas" x-data="{
        toast: null, toastTimer: null,
        show(text) {
            clearTimeout(this.toastTimer);
            this.toast = text;
            this.toastTimer = setTimeout(() => { this.toast = null }, 3400);
        },
     }"
     x-on:scope-item-saved.window="show(($event.detail.wasEdit ? 'Revised — ' : 'Added — ') + $event.detail.title)"
     x-on:scope-item-deleted.window="show('Removed — ' + $event.detail.title)">

    @if ($total === 0)
        <div class="cx-empty">
            {{-- Same hex-badge treatment the module header uses for its own
                 icon (colour mixed toward white, not transparent — Overview's
                 module colour IS the hero navy, so a transparent mix made
                 that badge disappear; the fix carries over here). --}}
            <span class="cx-cathex" style="width:44px;height:48px;margin:0 auto 14px;background: color-mix(in srgb, {{ \App\Models\Event::moduleColor('scope') }} 16%, white); color: {{ \App\Models\Event::moduleColor('scope') }}">
                <x-icon name="document" class="h-5 w-5" />
            </span>
            <h3>No scope written yet</h3>
            <p>Write what the client has asked us to deliver, type by type — and what is explicitly not included. The Event Brief reads this scope directly, so it only has to be written once.</p>
            @can('write')
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <button type="button" wire:click="newItem" class="cx-btn cx-btn-accent inline-flex items-center gap-1">
                        <x-icon name="plus" class="h-3.5 w-3.5" /> Write a deliverable
                    </button>
                    <button type="button" wire:click="newItem(true)" class="cx-btn cx-btn-ghost inline-flex items-center gap-1">
                        <x-icon name="plus" class="h-3.5 w-3.5" /> Note an exclusion
                    </button>
                </div>
            @endcan
        </div>
    @else
        {{-- No eyebrow or count here. The module header directly above this
             tab already states "N in scope · N excluded" as its own headline
             tiles — repeating the same two numbers in text a few pixels
             below them is the exact duplication this app's module headers
             have had to be cleared of more than once today. --}}
        <div class="mb-2 flex flex-wrap items-center justify-end gap-1.5">
            @can('write')
                <button type="button" wire:click="newItem" class="cx-btn cx-btn-accent inline-flex items-center gap-1" style="height:32px">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Scope line
                </button>
                <button type="button" wire:click="newItem(true)" class="cx-btn cx-btn-ghost inline-flex items-center gap-1" style="height:32px">
                    <x-icon name="plus" class="h-3.5 w-3.5" /> Exclusion
                </button>
            @endcan
        </div>

        {{-- ── What we are delivering ── --}}
        @foreach ($groups as $key => $group)
            <div class="cx-lcard">
                <div class="cx-lcard-head">
                    <span class="cx-lt">
                        <span class="cx-hexdot" style="background: {{ \App\Models\EventScopeItem::TYPES[$key][1] ?? 'var(--cx-muted)' }}"></span>
                        {{ $group['label'] }}
                    </span>
                    <span class="text-[10.5px] text-muted">{{ $group['rows']->count() }}</span>
                </div>

                @foreach ($group['rows'] as $item)
                    <div wire:key="s-{{ $item->id }}" class="flex items-start gap-2 border-b border-line px-3.5 py-3 last:border-0">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13.5px] font-semibold leading-snug text-ink">{{ $item->title }}</span>
                            @if ($item->body)
                                <span class="mt-1 block whitespace-pre-line text-[12px] leading-relaxed text-muted">{{ $item->body }}</span>
                            @endif
                            @if ($item->quantity || $item->owner)
                                <span class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                    @if ($item->quantity)
                                        <span class="inline-flex items-center rounded-full bg-page px-2 py-0.5 font-semibold text-ink">{{ $item->quantity }}</span>
                                    @endif
                                    @if ($item->owner)
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-user-avatar :user="$item->owner" size="h-4 w-4" />
                                            <span class="font-semibold text-ink">{{ $item->owner->name }}</span>
                                        </span>
                                    @endif
                                </span>
                            @endif
                        </span>

                        @can('write')
                            {{-- Always on screen — see the file header for why. Edit is the
                                 neutral action and sits first; delete is destructive, gets its
                                 own colour, and sits spatially apart from it. --}}
                            <span class="flex shrink-0 items-center gap-2">
                                <button type="button" wire:click="edit({{ $item->id }})"
                                        aria-label="Edit “{{ $item->title }}”" title="Edit"
                                        class="rounded-lg p-1.5 text-muted transition hover:bg-page hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-gold-500">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>
                                <x-confirm title="Remove this line from the scope?"
                                           body="It stops being something we have said we will deliver, and the Brief stops showing it."
                                           confirm="Remove"
                                           run="$wire.delete({{ $item->id }})"
                                           class="rounded-lg p-1.5 text-muted transition hover:bg-danger-soft hover:text-danger-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-danger">
                                    <span aria-hidden="true"><x-icon name="trash" class="h-4 w-4" /></span>
                                    <span class="sr-only">Remove “{{ $item->title }}”</span>
                                </x-confirm>
                            </span>
                        @endcan
                    </div>
                @endforeach

                @can('write')
                    {{-- Contextual add: already reading this type, so adding a second
                         line to it should not mean hunting through a picker again. --}}
                    <button type="button" wire:click="newItem(false, '{{ $key }}')"
                            class="flex w-full items-center gap-1.5 border-t border-line px-3.5 py-2 text-left text-[11.5px] font-semibold text-muted transition hover:bg-page hover:text-ink">
                        <x-icon name="plus" class="h-3 w-3" /> Add to {{ $group['label'] }}
                    </button>
                @endcan
            </div>
        @endforeach

        {{-- ── What we are not doing ──
             Kept together and last. The exclusions are the half of a scope
             that settles the argument three weeks out, so they are stated
             plainly rather than buried among the inclusions. --}}
        @if ($exclusions->isNotEmpty())
            <div class="cx-lcard">
                <div class="cx-lcard-head" style="background: var(--cx-warn-wash)">
                    <span class="cx-lt" style="color: var(--cx-warn-ink)">Not included in this scope</span>
                    <span class="text-[10.5px]" style="color: var(--cx-warn-ink)">{{ $exclusions->count() }}</span>
                </div>

                @foreach ($exclusions as $item)
                    <div wire:key="x-{{ $item->id }}" class="flex items-start gap-2 border-b border-line px-3.5 py-3 last:border-0">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13.5px] font-semibold leading-snug text-ink">{{ $item->title }}</span>
                            @if ($item->body)
                                <span class="mt-1 block whitespace-pre-line text-[12px] leading-relaxed text-muted">{{ $item->body }}</span>
                            @endif
                            @if ($item->quantity || $item->owner)
                                <span class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                    @if ($item->quantity)
                                        <span class="inline-flex items-center rounded-full bg-page px-2 py-0.5 font-semibold text-ink">{{ $item->quantity }}</span>
                                    @endif
                                    @if ($item->owner)
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-user-avatar :user="$item->owner" size="h-4 w-4" />
                                            <span class="font-semibold text-ink">{{ $item->owner->name }}</span>
                                        </span>
                                    @endif
                                </span>
                            @endif
                        </span>
                        @can('write')
                            <span class="flex shrink-0 items-center gap-2">
                                <button type="button" wire:click="edit({{ $item->id }})"
                                        aria-label="Edit “{{ $item->title }}”" title="Edit"
                                        class="rounded-lg p-1.5 text-muted transition hover:bg-page hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-gold-500">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>
                                <x-confirm title="Remove this exclusion?"
                                           confirm="Remove"
                                           run="$wire.delete({{ $item->id }})"
                                           class="rounded-lg p-1.5 text-muted transition hover:bg-danger-soft hover:text-danger-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-danger">
                                    <span aria-hidden="true"><x-icon name="trash" class="h-4 w-4" /></span>
                                    <span class="sr-only">Remove “{{ $item->title }}”</span>
                                </x-confirm>
                            </span>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif

        <p class="mt-2 text-[11px] text-muted">
            The Event Brief reads this scope directly — revise it here and the Brief follows.
        </p>
    @endif

    {{-- ── Write / revise, on the right ──
         The same fixed/slide-in mechanics as the hub's own utilities drawer
         (resources/css/event-hub.css's .ehx-utilities-drawer), not a
         centred dialog: this is where you park while you write a line and
         keep glancing back at the register beside it, not a single decision
         you make and dismiss.

         Kept in the DOM permanently (no @if) and entangled with the server
         property, the same idiom contract-tab.blade.php already uses for
         its own dirty-state banner — a Livewire @if would destroy and
         recreate this element every open/close, which is exactly what
         breaks a CSS slide transition (nothing to transition FROM) and what
         would have silently broken autofocus down to "fires once, on page
         load" instead of "fires every time the panel opens". --}}
    <div x-data="{ open: @entangle('showForm') }"
         x-effect="if (open) $nextTick(() => $refs.scopeTitle?.focus())"
         x-on:keydown.escape.window="open = false">

        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             x-on:click="open = false" class="cx-panel-backdrop"></div>

        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             role="dialog" aria-modal="true" aria-label="{{ $editingId ? 'Revise this line' : 'Write a scope line' }}"
             class="cx-panel">

            <div class="cx-panel-head">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="cx-eyebrow">{{ $editingId ? 'Revise this line' : ($is_exclusion ? 'Add an exclusion' : 'Add a deliverable') }}</p>
                        <p class="mt-0.5 text-[12px] text-muted">The Brief reads whatever is written here.</p>
                    </div>
                    <button type="button" x-on:click="open = false"
                            class="-me-1 shrink-0 rounded-lg p-1.5 text-muted transition hover:bg-page hover:text-ink"
                            aria-label="Close">✕</button>
                </div>

                {{-- What kind of line this is — decided first, because it changes
                     what every field below is even asking. A checkbox at the
                     bottom of the old form let this go unnoticed until after
                     something was typed; stated up top, it cannot be missed. --}}
                <div class="mt-3.5 grid grid-cols-2 gap-1.5 rounded-lg bg-page p-1">
                    <button type="button" wire:click="$set('is_exclusion', false)"
                            class="rounded-md px-3 py-1.5 text-[12.5px] font-bold transition {{ ! $is_exclusion ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink' }}">
                        Deliverable
                    </button>
                    <button type="button" wire:click="$set('is_exclusion', true)"
                            class="rounded-md px-3 py-1.5 text-[12.5px] font-bold transition {{ $is_exclusion ? 'bg-white text-warning-ink shadow-sm' : 'text-muted hover:text-ink' }}">
                        Exclusion
                    </button>
                </div>
            </div>

            <div class="cx-panel-body space-y-4">
                <div>
                    <label for="scope-title" class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">
                        {{ $is_exclusion ? 'What is not included' : 'What we will deliver' }}
                    </label>
                    <input id="scope-title" x-ref="scopeTitle" type="text" wire:model="title" class="eo-input"
                           aria-describedby="{{ $errors->has('title') ? 'scope-title-error' : '' }}"
                           placeholder="{{ $is_exclusion ? 'Simultaneous interpretation' : 'Full event management and on-site supervision' }}">
                    @error('title')
                        <p id="scope-title-error" role="alert" class="mt-1 text-[11px] text-danger-ink">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="scope-body" class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Detail (optional)</label>
                    <textarea id="scope-body" wire:model="body" rows="5" class="eo-textarea"
                              placeholder="{{ $is_exclusion ? 'The client contracts interpreters directly and supplies the booths.' : 'Planning, supplier coordination, run-of-show and an on-site team for the full event period.' }}"></textarea>
                </div>

                <div>
                    <label for="scope-type" class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Type</label>
                    <select id="scope-type" wire:model="type" class="eo-select">
                        @foreach ($types as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-muted">Editable in Settings → Types &amp; Lists → Scope types.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="scope-quantity" class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Quantity</label>
                        <input id="scope-quantity" type="text" wire:model="quantity" class="eo-input" placeholder="e.g. 3 rooms, 200 badges">
                    </div>
                    <div>
                        <label for="scope-owner" class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Owner</label>
                        <select id="scope-owner" wire:model="owner_id" class="eo-select">
                            <option value="">Nobody yet</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="cx-panel-foot">
                <button type="button" x-on:click="open = false" class="cx-btn cx-btn-ghost">Cancel</button>
                <button type="button" wire:click="save" class="cx-btn cx-btn-accent ms-auto">{{ $editingId ? 'Save changes' : 'Add to scope' }}</button>
            </div>
        </div>
    </div>

    {{-- ── Confirmation toast ──
         Not a modal that closing already implied success — a distinct
         acknowledgement, since the row it affected may be off-screen among a
         dozen others. Auto-dismisses; aria-live so it reaches screen readers
         without stealing focus from wherever the user's attention already is. --}}
    <div x-show="toast" x-transition.opacity.duration.200ms
         x-cloak
         role="status" aria-live="polite"
         class="fixed bottom-5 right-5 z-[60] flex items-center gap-2 rounded-lg border border-line bg-navy-900 px-4 py-2.5 text-[12.5px] font-semibold text-white shadow-raise">
        <x-icon name="check" class="h-4 w-4 shrink-0 text-success" />
        <span x-text="toast"></span>
    </div>
</div>
