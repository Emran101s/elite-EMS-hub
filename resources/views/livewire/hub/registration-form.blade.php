@php
    use App\Models\RegistrationField;
    $may = auth()->user()?->can('write') ?? false;
@endphp

<div class="space-y-4">

    {{-- ══ what this is ══ --}}
    <div class="card flex flex-wrap items-center gap-3 p-4">
        <span class="min-w-0">
            <span class="block text-[13px] font-bold text-navy-900">The questions this event asks</span>
            <span class="block text-[11.5px] text-muted">
                {{ $fields->count() }} {{ str('question')->plural($fields->count()) }} ·
                what a registrant sees at <a href="{{ $event->registrationUrl() }}" target="_blank" class="font-semibold text-indigo-600 hover:underline">the public link</a>.
            </span>
        </span>

        @if ($may)
            <span class="ms-auto flex items-center gap-2">
                {{-- Start from a form you keep. Adds by default rather than
                     replaces: an event that has taken registrations has answers
                     filed against its questions. --}}
                @if ($templates->isNotEmpty())
                    <details class="relative" data-menu>
                        <summary class="flex h-9 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 transition hover:border-gold-300 [&::-webkit-details-marker]:hidden">
                            ⇪ From a template
                        </summary>

                        <div class="absolute end-0 z-30 mt-1.5 w-80 overflow-hidden rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                            @foreach ($templates as $t)
                                <button type="button"
                                        wire:click="$set('templateId', {{ $t->id }})"
                                        @class(['flex w-full flex-col rounded-xl px-2.5 py-2 text-start transition',
                                            'bg-navy-950 text-white' => $templateId === $t->id,
                                            'hover:bg-page' => $templateId !== $t->id])>
                                    <span class="text-[12px] font-bold">{{ $t->name }}</span>
                                    <span @class(['text-[10.5px]', 'text-white/60' => $templateId === $t->id, 'text-muted' => $templateId !== $t->id])>
                                        {{ $t->questions()->count() }} {{ str('question')->plural($t->questions()->count()) }}@if ($t->note) · {{ $t->note }} @endif
                                    </span>
                                </button>
                            @endforeach

                            @if ($templateId)
                                <div class="mt-1.5 border-t border-line pt-2">
                                    <label class="flex cursor-pointer items-start gap-2 px-2.5 pb-2 text-[11px] text-navy-600">
                                        <input type="checkbox" wire:model="replaceForm" class="mt-0.5 h-3.5 w-3.5 rounded border-navy-300">
                                        <span>Replace this event's questions — name and email are kept whatever happens.</span>
                                    </label>
                                    <button type="button" wire:click="applyTemplate"
                                            class="w-full rounded-xl bg-navy-950 px-3 py-2 text-[11.5px] font-bold text-white transition hover:bg-navy-800">
                                        {{ $replaceForm ? 'Replace the form' : 'Add its questions' }}
                                    </button>
                                </div>
                            @endif

                            <a href="{{ route('registration-templates.index') }}" wire:navigate
                               class="mt-1 block px-2.5 py-1.5 text-[10.5px] font-semibold text-navy-400 transition hover:text-indigo-600">Manage templates →</a>
                        </div>
                    </details>
                @endif

                <button type="button" wire:click="newField"
                        class="flex h-9 items-center rounded-2xl bg-navy-950 px-4 text-[12px] font-bold text-white transition hover:bg-navy-800">
                    ＋ Add a question
                </button>
            </span>
        @endif
    </div>

    @if ($templateFlash)
        <p class="rounded-2xl bg-emerald-50 px-4 py-2.5 text-[12px] font-semibold text-emerald-800">{{ $templateFlash }}</p>
    @endif

    {{-- ══ the editor ══ --}}
    @if ($editingId !== null)
        <div class="card border-gold-300 bg-gold-50/40 p-4">
            <p class="field-label !mb-3">{{ $editingId ? 'Edit the question' : 'A new question' }}</p>

            <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Question</span>
                    <input type="text" wire:model="label" placeholder="Which workshop track will you attend?"
                           class="input h-9 w-full text-xs">
                    @error('label') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Answer type</span>
                    <select wire:model.live="type" class="input h-9 w-full text-xs">
                        @foreach ($types as $key => [$name, $takesOptions, $note])
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-[10.5px] text-muted">{{ $types[$type][2] ?? '' }}</span>
                </label>

                @if ($types[$type][1] ?? false)
                    <label class="block sm:col-span-2">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Choices — one per line</span>
                        <textarea rows="4" wire:model="optionsText" class="input w-full text-xs"
                                  placeholder="Track A — Policy&#10;Track B — Practice&#10;Track C — Research"></textarea>
                        @error('optionsText') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                    </label>
                @endif

                @unless ($editingId)
                    <label class="block">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Fills</span>
                        <select wire:model="maps_to" class="input h-9 w-full text-xs">
                            <option value="">Its own answer</option>
                            @foreach ($columns as $col => $name)
                                <option value="{{ $col }}" @disabled(in_array($col, $taken, true))>
                                    {{ $name }}{{ in_array($col, $taken, true) ? ' — already asked' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-[10.5px] leading-snug text-muted">
                            A question can fill one of the columns the badge and check-in read. Everything else is kept as an answer.
                        </span>
                    </label>
                @endunless

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Hint under the field</span>
                    <input type="text" wire:model="help" placeholder="Optional" class="input h-9 w-full text-xs">
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Placeholder</span>
                    <input type="text" wire:model="placeholder" placeholder="Optional" class="input h-9 w-full text-xs">
                </label>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="save"
                        class="rounded-xl bg-navy-950 px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-navy-800">
                    {{ $editingId ? 'Save' : 'Add it' }}
                </button>
                <button type="button" wire:click="cancel"
                        class="rounded-xl px-3 py-2 text-[12px] font-bold text-navy-400 transition hover:text-navy-700">Cancel</button>

                <label class="ms-2 flex cursor-pointer items-center gap-2 text-[12px] font-semibold text-navy-600">
                    <input type="checkbox" wire:model="required" class="h-3.5 w-3.5 rounded border-navy-300"> Must be answered
                </label>
            </div>
        </div>
    @endif

    {{-- ══ the form as it stands ══ --}}
    <div class="card overflow-hidden">
        @foreach ($fields as $field)
            <div wire:key="rf-{{ $field->id }}"
                 class="flex items-center gap-3 border-b border-line/60 px-4 py-2.5 last:border-0 hover:bg-navy-50/30">

                <span class="flex w-6 shrink-0 flex-col text-[9px] leading-none text-navy-300">
                    @if ($may && ! $loop->first)
                        <button type="button" wire:click="move({{ $field->id }}, -1)" class="hover:text-navy-700">▲</button>
                    @endif
                    @if ($may && ! $loop->last)
                        <button type="button" wire:click="move({{ $field->id }}, 1)" class="mt-0.5 hover:text-navy-700">▼</button>
                    @endif
                </span>

                <button type="button" @if ($may) wire:click="edit({{ $field->id }})" @endif class="min-w-0 flex-1 text-start">
                    <span class="block truncate text-[12.5px] font-bold text-navy-900">
                        {{ $field->label }}
                        @if ($field->required)<span class="text-risk">*</span>@endif
                    </span>
                    <span class="block truncate text-[10.5px] text-muted">
                        {{ $field->typeLabel() }}
                        @if ($field->options) · {{ count($field->options) }} {{ str('choice')->plural(count($field->options)) }} @endif
                        @if ($field->help) · {{ $field->help }} @endif
                    </span>
                </button>

                {{-- Where the answer goes. A question that fills a column the
                     platform reads by name is worth marking, because removing
                     it later changes more than the form. --}}
                @if ($field->isCore())
                    <span class="hidden shrink-0 rounded-full bg-navy-50 px-2 py-0.5 text-[10px] font-bold text-navy-500 sm:block"
                          title="Fills the attendee's {{ $columns[$field->maps_to] ?? $field->maps_to }}">
                        {{ $columns[$field->maps_to] ?? $field->maps_to }}
                    </span>
                @else
                    <span class="hidden shrink-0 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-600 sm:block"
                          title="Kept as an answer under “{{ $field->key }}”">Answer</span>
                @endif

                <span class="w-20 shrink-0 text-end">
                    @if ($may && ! $field->isLocked())
                        <button type="button" wire:click="remove({{ $field->id }})"
                                wire:confirm="Take “{{ $field->label }}” off the form?&#10;&#10;Answers already given are kept on the people who gave them."
                                class="rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">Remove</button>
                    @elseif ($field->isLocked())
                        <span class="text-[10.5px] italic text-navy-300" title="A person cannot be identified, badged or checked in without this">Always asked</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
</div>
