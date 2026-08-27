    {{-- ══════════ Add / Edit Session modal ══════════ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit Session' : 'Add Session'" max="2xl" close="closeForm" flush wire:key="session-modal">
                <form wire:submit="saveSession">

                    <div class="space-y-4 px-6 py-5">
                        <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                            <div>
                                <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-title">Title <span class="text-danger-ink">*</span></label>
                                <input id="m-title" type="text" wire:model="title" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="Opening Keynote">
                                @error('title') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:w-44">
                                <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-type">Type</label>
                                <input id="m-type" type="text" list="session-types" wire:model="type" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none"
                                       autocomplete="off" placeholder="Keynote, Setup, Rehearsal…">
                                <datalist id="session-types">
                                    @foreach ($typeOptions as $t)<option value="{{ $t }}"></option>@endforeach
                                </datalist>
                                <p class="mt-1 text-[10.5px] text-muted">Pick one or type your own.</p>
                                @error('type') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- The track is not a label: it decides where the session
                             sits on the programme and whether a delegate sees it
                             at all. Free text, because every event invents one. --}}
                        <div>
                            <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-track">Programme track <span class="font-normal normal-case tracking-normal text-muted">optional</span></label>
                            <input id="m-track" type="text" list="session-tracks" wire:model="track" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none"
                                   autocomplete="off" placeholder="Main Stage, Track A, Registration…">
                            <datalist id="session-tracks">
                                @foreach ($trackOptions as $name => $does)<option value="{{ $name }}">{{ $does }}</option>@endforeach
                            </datalist>
                            <p class="mt-1 text-[10.5px] text-muted">Main Stage heads its slot; Registration, Setup, Media and Partnerships stay off the public programme.</p>
                            @error('track') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Format</label>
                            <div class="grid grid-cols-3 gap-2.5">
                                @foreach (\App\Support\Taxonomy::options('session_format') as $val => $lbl)
                                    <button type="button" wire:click="$set('format', '{{ $val }}')"
                                            @class([
                                                'rounded-2xl border py-2.5 text-sm font-bold transition',
                                                'border-navy-900 bg-navy-900 text-white' => $format === $val,
                                                'border-line text-muted hover:text-ink' => $format !== $val,
                                            ])>{{ $lbl }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-status">Status</label>
                            <select id="m-status" wire:model="status" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                                @foreach (\App\Models\EventAgendaSession::STATUS_META as $val => [$lbl, $settled])
                                    <option value="{{ $val }}">{{ $lbl }}{{ $settled ? '' : ' — not for distribution' }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-cap">Seat capacity <span class="font-normal normal-case tracking-normal text-muted">optional — limits attendee sign-ups</span></label>
                            <input id="m-cap" type="number" min="0" wire:model="capacity" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="Leave blank for unlimited">
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-day">Day <span class="text-danger-ink">*</span></label>
                                <select id="m-day" wire:model="agenda_day_id" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                                    @foreach ($days as $d)<option value="{{ $d->id }}">Day {{ $loop->iteration }} — {{ $d->date?->format('D j M') }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-start">Start <span class="text-danger-ink">*</span></label>
                                <input id="m-start" type="time" wire:model="starts_at" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-end">End <span class="text-danger-ink">*</span></label>
                                <input id="m-end" type="time" wire:model="ends_at" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="mb-0 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Speaker line-up</label>
                                    <span class="text-[10.5px] text-muted">Pick from the roster or type a new name — new people are added to Speakers automatically</span>
                                </div>

                                <datalist id="speaker-roster">
                                    @foreach ($speakerOptions as $sp)<option value="{{ $sp }}"></option>@endforeach
                                </datalist>

                                <div class="space-y-2">
                                    @forelse ($speakerRows as $i => $row)
                                        <div class="flex items-center gap-2" wire:key="spk-{{ $i }}">
                                            <input type="text" list="speaker-roster" wire:model.blur="speakerRows.{{ $i }}.name"
                                                   class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none flex-1" placeholder="Speaker name">
                                            <select wire:model="speakerRows.{{ $i }}.role" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink focus:border-navy-300 focus:outline-none w-40 shrink-0">
                                                @foreach ($roles as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="removeSpeakerRow({{ $i }})"
                                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-muted transition hover:bg-danger-soft hover:text-danger-ink"
                                                    title="Remove">✕</button>
                                        </div>
                                    @empty
                                        <p class="rounded-xl border border-dashed border-line px-3 py-3 text-center text-[11px] text-muted">
                                            No speakers billed yet — a panel can carry a moderator plus several panellists.
                                        </p>
                                    @endforelse
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" wire:click="addSpeakerRow('keynote')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-[11px] font-semibold text-ink transition hover:border-gold-400/40">＋ Keynote</button>
                                    <button type="button" wire:click="addSpeakerRow('moderator')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-[11px] font-semibold text-ink transition hover:border-gold-400/40">＋ Moderator</button>
                                    <button type="button" wire:click="addSpeakerRow('panelist')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-[11px] font-semibold text-ink transition hover:border-gold-400/40">＋ Panellist</button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-room">Venue / Room</label>
                                <select id="m-room" wire:model="room_id" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink focus:border-navy-300 focus:outline-none mb-2">
                                    <option value="">— Select a venue —</option>
                                    @foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach
                                </select>
                                <input type="text" wire:model="newRoomName" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="…or type a room">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="m-desc">Description / Notes</label>
                            <textarea id="m-desc" wire:model="description" rows="2" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="Optional details…"></textarea>
                        </div>
                    </div>

                    {{-- footer --}}
                    <div class="flex items-center justify-end gap-3 rounded-b-[24px] border-t border-line bg-page/60 px-6 py-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-ink">
                            <input type="checkbox" wire:model="flagged" class="h-4 w-4 rounded border-line text-gold-600 focus:ring-gold-400">
                            <span>🚩 Flag</span>
                        </label>
                        @if ($editingId)
                            <x-confirm title="Delete this session?"
                                       confirm="Delete"
                                       run="$wire.deleteSession({{ $editingId }})"
                                       class="rounded-2xl px-4 py-2.5 text-sm font-bold text-danger-ink transition hover:bg-danger-soft">Delete</x-confirm>
                        @endif
                        <span class="mr-auto"></span>
                        <button type="button" wire:click="closeForm" class="rounded-2xl bg-page px-6 py-2.5 text-sm font-bold text-ink transition hover:bg-line">Cancel</button>
                        <button type="submit" class="rounded-2xl bg-gold-500 px-7 py-2.5 text-sm font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">{{ $editingId ? 'Save Session' : 'Add Session' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif

