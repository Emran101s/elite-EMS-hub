@php
    $may = auth()->user()?->can('write') ?? false;
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] text-eo-gold">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-eo-gold-soft/30 text-eo-gold"><x-icon name="clipboard" class="h-3.5 w-3.5" /></span>
                Registration templates
            </p>
            <p class="mt-1.5 text-[12.5px] text-eo-muted">
                {{ $templates->count() }} {{ str('template')->plural($templates->count()) }} ·
                an event starts from a copy and owns it after, so editing one here never rewrites a form already answered.
            </p>
        </div>

        @if ($may)
            <x-eo.button size="sm" wire:click="newTemplate" class="h-10">
                ＋ New template
            </x-eo.button>
        @endif
    </div>

    {{-- ══ the editor ══ --}}
    @if ($editingId !== null)
        <div class="eo-soft-card border border-eo-gold/30 bg-eo-gold-soft/10 p-4">
            <div class="grid gap-2.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <label class="block">
                    <span class="eo-label mb-1 block">Name</span>
                    <input type="text" wire:model="name" placeholder="Conference & Summit" class="eo-input h-9 w-full text-xs">
                    @error('name') <p class="mt-1 text-[11px] font-semibold text-eo-risk">{{ $message }}</p> @enderror
                </label>
                <label class="block">
                    <span class="eo-label mb-1 block">What it is for</span>
                    <input type="text" wire:model="note" placeholder="Delegates, sessions and a dinner." class="eo-input h-9 w-full text-xs">
                </label>
            </div>

            <div class="mt-4 flex items-baseline gap-2 border-t border-eo-line pt-3">
                <p class="eo-label">The questions</p>
                <button type="button" wire:click="addQuestion" class="eo-btn-ghost eo-btn-sm ms-auto">
                    ＋ Add a question
                </button>
            </div>

            @forelse ($fields as $i => $f)
                <div wire:key="q-{{ $i }}" class="mt-2 rounded-2xl border border-eo-line bg-white p-3">
                    <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                        <label class="block sm:col-span-2">
                            <span class="eo-label mb-1 block">Question</span>
                            <input type="text" wire:model="fields.{{ $i }}.label" class="eo-input h-9 w-full text-xs"
                                   placeholder="Which workshop track will you attend?">
                            @error('fields.'.$i.'.label') <p class="mt-1 text-[11px] font-semibold text-eo-risk">{{ $message }}</p> @enderror
                        </label>

                        <label class="block">
                            <span class="eo-label mb-1 block">Answer type</span>
                            <select wire:model.live="fields.{{ $i }}.type" class="eo-select h-9 w-full text-xs">
                                @foreach ($types as $key => [$label, $takesOptions, $note])
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="eo-label mb-1 block">Fills</span>
                            <select wire:model="fields.{{ $i }}.maps_to" class="eo-select h-9 w-full text-xs">
                                <option value="">Its own answer</option>
                                @foreach ($columns as $col => $label)
                                    <option value="{{ $col }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        @if ($types[$f['type'] ?? 'text'][1] ?? false)
                            <label class="block sm:col-span-2 xl:col-span-4">
                                <span class="eo-label mb-1 block">Choices — one per line</span>
                                <textarea rows="3" class="eo-textarea w-full text-xs"
                                          wire:model="fields.{{ $i }}.options"
                                          placeholder="Track A&#10;Track B">{{ is_array($f['options'] ?? null) ? implode("\n", $f['options']) : ($f['options'] ?? '') }}</textarea>
                            </label>
                        @endif

                        <label class="block sm:col-span-2">
                            <span class="eo-label mb-1 block">Hint under the field</span>
                            <input type="text" wire:model="fields.{{ $i }}.help" class="eo-input h-9 w-full text-xs" placeholder="Optional">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="eo-label mb-1 block">Placeholder</span>
                            <input type="text" wire:model="fields.{{ $i }}.placeholder" class="eo-input h-9 w-full text-xs" placeholder="Optional">
                        </label>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <label class="flex cursor-pointer items-center gap-2 text-[11.5px] font-semibold text-eo-text">
                            <input type="checkbox" wire:model="fields.{{ $i }}.required" class="h-3.5 w-3.5 rounded border-eo-line">
                            Must be answered
                        </label>

                        <span class="ms-auto flex items-center gap-1 text-[10px] text-eo-muted">
                            @unless ($loop->first)
                                <button type="button" wire:click="moveQuestion({{ $i }}, -1)" class="hover:text-eo-text">▲</button>
                            @endunless
                            @unless ($loop->last)
                                <button type="button" wire:click="moveQuestion({{ $i }}, 1)" class="hover:text-eo-text">▼</button>
                            @endunless
                        </span>

                        <button type="button" wire:click="removeQuestion({{ $i }})"
                                class="text-[11.5px] font-semibold text-eo-muted transition hover:text-eo-risk">Remove</button>
                    </div>
                </div>
            @empty
                <p class="mt-2 rounded-2xl border border-dashed border-eo-line py-6 text-center text-[12px] italic text-eo-muted">
                    No questions yet — add the first one.
                </p>
            @endforelse

            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-eo-line pt-3">
                <x-eo.button size="sm" wire:click="save">
                    {{ $editingId ? 'Save template' : 'Create it' }}
                </x-eo.button>
                <button type="button" wire:click="cancel" class="eo-btn-ghost eo-btn-sm">Cancel</button>

                @if ($editingId)
                    <x-confirm title="Delete this template?"
                               body="Events already started from it keep the form they have — a template is copied, not linked."
                               confirm="Delete" run="$wire.destroy({{ $editingId }})"
                               class="ms-auto rounded-xl px-3 py-2 text-[12px] font-bold text-eo-muted transition hover:bg-eo-risk/10 hover:text-eo-risk">Delete</x-confirm>
                @endif
            </div>
        </div>
    @endif

    {{-- ══ the library ══
         A copy-then-owns library reads best as a shelf of covers, not a plain
         list — the icon badge and question-count chip are the same pairing
         the stat tiles use everywhere else in the platform (Attendees, Risks,
         Venue…), so choosing a template feels like the same app as running
         the event it starts. --}}
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($templates as $t)
            <div wire:key="tpl-{{ $t->id }}" class="eo-soft-card relative flex flex-col overflow-hidden p-4 transition hover:-translate-y-0.5">
                <span class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-eo-gold/70 to-transparent" aria-hidden="true"></span>
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-eo-gold-soft/30 text-eo-gold">
                        <x-icon name="clipboard" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-bold text-eo-text">{{ $t->name }}</p>
                        @if ($t->note)
                            <p class="mt-0.5 line-clamp-2 text-[11.5px] text-eo-muted">{{ $t->note }}</p>
                        @endif
                    </div>
                </div>

                <span class="eo-pill eo-pill-premium mt-3 self-start">
                    {{ $t->questions()->count() }} {{ str('question')->plural($t->questions()->count()) }}
                </span>

                <p class="mt-2 line-clamp-3 text-[11px] leading-relaxed text-eo-muted">
                    {{ $t->questions()->pluck('label')->join(' · ') }}
                </p>

                @if ($may)
                    <div class="mt-3 flex items-center gap-1.5 border-t border-eo-line pt-2.5">
                        <button type="button" wire:click="edit({{ $t->id }})"
                                class="rounded-lg bg-eo-bg px-2.5 py-1.5 text-[11px] font-bold text-eo-text transition hover:bg-eo-line">Edit</button>
                        <button type="button" wire:click="duplicate({{ $t->id }})"
                                class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-eo-muted transition hover:text-eo-text">Duplicate</button>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full">
                <x-eo.empty-state icon="clipboard" title="No templates yet"
                         hint="Build one here, or take one from an event whose form you already like." />
            </div>
        @endforelse
    </div>
</div>
