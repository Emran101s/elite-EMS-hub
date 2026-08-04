@php
    $may = auth()->user()?->can('write') ?? false;
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-3">
        <p class="text-[11.5px] text-muted">
            {{ $templates->count() }} {{ str('template')->plural($templates->count()) }} ·
            an event starts from a copy and owns it after, so editing one here never rewrites a form already answered.
        </p>

        @if ($may)
            <button type="button" wire:click="newTemplate"
                    class="ms-auto flex h-10 items-center rounded-2xl bg-navy-950 px-4 text-[12px] font-bold text-white shadow-[0_10px_24px_-14px_rgba(11,31,58,0.9)] transition hover:bg-navy-800">
                ＋ New template
            </button>
        @endif
    </div>

    {{-- ══ the editor ══ --}}
    @if ($editingId !== null)
        <div class="card border-gold-300 bg-gold-50/40 p-4">
            <div class="grid gap-2.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Name</span>
                    <input type="text" wire:model="name" placeholder="Conference & Summit" class="input h-9 w-full text-xs">
                    @error('name') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">What it is for</span>
                    <input type="text" wire:model="note" placeholder="Delegates, sessions and a dinner." class="input h-9 w-full text-xs">
                </label>
            </div>

            <div class="mt-4 flex items-baseline gap-2 border-t border-line pt-3">
                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">The questions</p>
                <button type="button" wire:click="addQuestion"
                        class="ms-auto rounded-xl border border-line bg-white px-3 py-1.5 text-[11.5px] font-semibold text-navy-700 transition hover:border-gold-300">
                    ＋ Add a question
                </button>
            </div>

            @forelse ($fields as $i => $f)
                <div wire:key="q-{{ $i }}" class="mt-2 rounded-2xl border border-line bg-white p-3">
                    <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                        <label class="block sm:col-span-2">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Question</span>
                            <input type="text" wire:model="fields.{{ $i }}.label" class="input h-9 w-full text-xs"
                                   placeholder="Which workshop track will you attend?">
                            @error('fields.'.$i.'.label') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Answer type</span>
                            <select wire:model.live="fields.{{ $i }}.type" class="input h-9 w-full text-xs">
                                @foreach ($types as $key => [$label, $takesOptions, $note])
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Fills</span>
                            <select wire:model="fields.{{ $i }}.maps_to" class="input h-9 w-full text-xs">
                                <option value="">Its own answer</option>
                                @foreach ($columns as $col => $label)
                                    <option value="{{ $col }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        @if ($types[$f['type'] ?? 'text'][1] ?? false)
                            <label class="block sm:col-span-2 xl:col-span-4">
                                <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Choices — one per line</span>
                                <textarea rows="3" class="input w-full text-xs"
                                          wire:model="fields.{{ $i }}.options"
                                          placeholder="Track A&#10;Track B">{{ is_array($f['options'] ?? null) ? implode("\n", $f['options']) : ($f['options'] ?? '') }}</textarea>
                            </label>
                        @endif

                        <label class="block sm:col-span-2">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Hint under the field</span>
                            <input type="text" wire:model="fields.{{ $i }}.help" class="input h-9 w-full text-xs" placeholder="Optional">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Placeholder</span>
                            <input type="text" wire:model="fields.{{ $i }}.placeholder" class="input h-9 w-full text-xs" placeholder="Optional">
                        </label>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <label class="flex cursor-pointer items-center gap-2 text-[11.5px] font-semibold text-navy-600">
                            <input type="checkbox" wire:model="fields.{{ $i }}.required" class="h-3.5 w-3.5 rounded border-navy-300">
                            Must be answered
                        </label>

                        <span class="ms-auto flex items-center gap-1 text-[10px] text-navy-300">
                            @unless ($loop->first)
                                <button type="button" wire:click="moveQuestion({{ $i }}, -1)" class="hover:text-navy-700">▲</button>
                            @endunless
                            @unless ($loop->last)
                                <button type="button" wire:click="moveQuestion({{ $i }}, 1)" class="hover:text-navy-700">▼</button>
                            @endunless
                        </span>

                        <button type="button" wire:click="removeQuestion({{ $i }})"
                                class="text-[11.5px] font-semibold text-navy-400 transition hover:text-red-600">Remove</button>
                    </div>
                </div>
            @empty
                <p class="mt-2 rounded-2xl border border-dashed border-line py-6 text-center text-[12px] italic text-navy-300">
                    No questions yet — add the first one.
                </p>
            @endforelse

            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
                <button type="button" wire:click="save"
                        class="rounded-xl bg-navy-950 px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-navy-800">
                    {{ $editingId ? 'Save template' : 'Create it' }}
                </button>
                <button type="button" wire:click="cancel"
                        class="rounded-xl px-3 py-2 text-[12px] font-bold text-navy-400 transition hover:text-navy-700">Cancel</button>

                @if ($editingId)
                    <button type="button" wire:click="destroy({{ $editingId }})"
                            wire:confirm="Delete this template?&#10;&#10;Events already started from it keep the form they have — a template is copied, not linked."
                            class="ms-auto rounded-xl px-3 py-2 text-[12px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">Delete</button>
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
            <div wire:key="tpl-{{ $t->id }}" class="card flex flex-col p-4 transition hover:shadow-[0_18px_40px_-24px_rgba(11,31,58,0.35)]">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-500">
                        <x-icon name="clipboard" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-bold text-navy-900">{{ $t->name }}</p>
                        @if ($t->note)
                            <p class="mt-0.5 line-clamp-2 text-[11.5px] text-muted">{{ $t->note }}</p>
                        @endif
                    </div>
                </div>

                <span class="chip-gold mt-3 self-start">
                    {{ $t->questions()->count() }} {{ str('question')->plural($t->questions()->count()) }}
                </span>

                <p class="mt-2 line-clamp-3 text-[11px] leading-relaxed text-muted">
                    {{ $t->questions()->pluck('label')->join(' · ') }}
                </p>

                @if ($may)
                    <div class="mt-3 flex items-center gap-1.5 border-t border-line pt-2.5">
                        <button type="button" wire:click="edit({{ $t->id }})"
                                class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-[11px] font-bold text-navy-700 transition hover:bg-navy-100">Edit</button>
                        <button type="button" wire:click="duplicate({{ $t->id }})"
                                class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-navy-400 transition hover:text-navy-700">Duplicate</button>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full">
                <x-empty icon="clipboard" title="No templates yet"
                         hint="Build one here, or take one from an event whose form you already like." />
            </div>
        @endforelse
    </div>
</div>
