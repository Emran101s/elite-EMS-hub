@php
    $may = auth()->user()?->can('write') ?? false;
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] text-gold-700">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-gold-50/30 text-gold-700"><x-icon name="clipboard" class="h-3.5 w-3.5" /></span>
                Registration templates
            </p>
            <p class="mt-1.5 text-[12.5px] text-muted">
                {{ $templates->count() }} {{ str('template')->plural($templates->count()) }} ·
                an event starts from a copy and owns it after, so editing one here never rewrites a form already answered.
            </p>
        </div>

        @if ($may)
            <button type="button" wire:click="newTemplate" class="h-10 rounded-full bg-gold-500 px-4 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                ＋ New template
            </button>
        @endif
    </div>

    {{-- ══ the editor ══ --}}
    @if ($editingId !== null)
        <div class="rounded-lg border border-line bg-white shadow-raise border border-gold-400/30 bg-gold-50/10 p-4">
            <div class="grid gap-2.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <label class="block">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">Name</span>
                    <input type="text" wire:model="name" placeholder="Conference & Summit" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                    @error('name') <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                </label>
                <label class="block">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">What it is for</span>
                    <input type="text" wire:model="note" placeholder="Delegates, sessions and a dinner." class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                </label>
            </div>

            <div class="mt-4 flex items-baseline gap-2 border-t border-line pt-3">
                <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">The questions</p>
                <button type="button" wire:click="addQuestion" class="rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink transition hover:border-gold-300 ms-auto">
                    ＋ Add a question
                </button>
            </div>

            @forelse ($fields as $i => $f)
                <div wire:key="q-{{ $i }}" class="mt-2 rounded-2xl border border-line bg-white p-3">
                    <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                        <label class="block sm:col-span-2">
                            <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">Question</span>
                            <input type="text" wire:model="fields.{{ $i }}.label" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs"
                                   placeholder="Which workshop track will you attend?">
                            @error('fields.'.$i.'.label') <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                        </label>

                        <label class="block">
                            <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">Answer type</span>
                            <select wire:model.live="fields.{{ $i }}.type" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                                @foreach ($types as $key => [$label, $takesOptions, $note])
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">Fills</span>
                            <select wire:model="fields.{{ $i }}.maps_to" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                                <option value="">Its own answer</option>
                                @foreach ($columns as $col => $label)
                                    <option value="{{ $col }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        @if ($types[$f['type'] ?? 'text'][1] ?? false)
                            <label class="block sm:col-span-2 xl:col-span-4">
                                <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">Choices — one per line</span>
                                <textarea rows="3" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none w-full text-xs"
                                          wire:model="fields.{{ $i }}.options"
                                          placeholder="Track A&#10;Track B">{{ is_array($f['options'] ?? null) ? implode("\n", $f['options']) : ($f['options'] ?? '') }}</textarea>
                            </label>
                        @endif

                        <label class="block sm:col-span-2">
                            <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">Hint under the field</span>
                            <input type="text" wire:model="fields.{{ $i }}.help" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs" placeholder="Optional">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">Placeholder</span>
                            <input type="text" wire:model="fields.{{ $i }}.placeholder" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs" placeholder="Optional">
                        </label>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <label class="flex cursor-pointer items-center gap-2 text-[11.5px] font-semibold text-ink">
                            <input type="checkbox" wire:model="fields.{{ $i }}.required" class="h-3.5 w-3.5 rounded border-line">
                            Must be answered
                        </label>

                        <span class="ms-auto flex items-center gap-1 text-[10px] text-muted">
                            @unless ($loop->first)
                                <button type="button" wire:click="moveQuestion({{ $i }}, -1)" class="hover:text-ink">▲</button>
                            @endunless
                            @unless ($loop->last)
                                <button type="button" wire:click="moveQuestion({{ $i }}, 1)" class="hover:text-ink">▼</button>
                            @endunless
                        </span>

                        <button type="button" wire:click="removeQuestion({{ $i }})"
                                class="text-[11.5px] font-semibold text-muted transition hover:text-danger-ink">Remove</button>
                    </div>
                </div>
            @empty
                <p class="mt-2 rounded-2xl border border-dashed border-line py-6 text-center text-[12px] italic text-muted">
                    No questions yet — add the first one.
                </p>
            @endforelse

            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
                <button type="button" wire:click="save" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                    {{ $editingId ? 'Save template' : 'Create it' }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink transition hover:border-gold-300">Cancel</button>

                @if ($editingId)
                    <x-confirm title="Delete this template?"
                               body="Events already started from it keep the form they have — a template is copied, not linked."
                               confirm="Delete" run="$wire.destroy({{ $editingId }})"
                               class="ms-auto rounded-xl px-3 py-2 text-[12px] font-bold text-muted transition hover:bg-danger/10 hover:text-danger-ink">Delete</x-confirm>
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
            <div wire:key="tpl-{{ $t->id }}" class="rounded-lg border border-line bg-white shadow-raise relative flex flex-col overflow-hidden p-4 transition hover:-translate-y-0.5">
                <span class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-gold-500/70 to-transparent" aria-hidden="true"></span>
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gold-50/30 text-gold-700">
                        <x-icon name="clipboard" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-bold text-ink">{{ $t->name }}</p>
                        @if ($t->note)
                            <p class="mt-0.5 line-clamp-2 text-[11.5px] text-muted">{{ $t->note }}</p>
                        @endif
                    </div>
                </div>

                <span class="inline-flex items-center rounded-full bg-gold-50 px-2 py-0.5 text-[10px] font-bold text-gold-700 ring-1 ring-gold-200 mt-3 self-start">
                    {{ $t->questions()->count() }} {{ str('question')->plural($t->questions()->count()) }}
                </span>

                <p class="mt-2 line-clamp-3 text-[11px] leading-relaxed text-muted">
                    {{ $t->questions()->pluck('label')->join(' · ') }}
                </p>

                @if ($may)
                    <div class="mt-3 flex items-center gap-1.5 border-t border-line pt-2.5">
                        <button type="button" wire:click="edit({{ $t->id }})"
                                class="rounded-lg bg-page px-2.5 py-1.5 text-[11px] font-bold text-ink transition hover:bg-line">Edit</button>
                        <button type="button" wire:click="duplicate({{ $t->id }})"
                                class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-muted transition hover:text-ink">Duplicate</button>
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
