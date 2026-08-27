@php
    // Per-deal and per-event figures print in THAT record's own currency —
    // a client with both a JOD and a USD deal used to see every figure
    // labelled "JD" regardless, which is wrong whenever the two disagree.
    // The two portfolio totals below (lifetime value, pipeline value) still
    // sum every record's cents together with no currency conversion — a
    // pre-existing gap this pass didn't introduce and formatting alone can't
    // fix — so they're labelled in the house currency as the closest honest
    // reading, not a claim that the sum is currency-correct.
    $money = fn (int $cents, ?string $cur = null) => \App\Support\Money::abbreviated($cents, $cur ?? \App\Models\CompanyProfile::currency());
@endphp

<div class="space-y-5">

    <x-cc.header eyebrow="Commercial Command" :title="$client->name" subtitle="Client record — people, deals, events and every conversation.">
        <x-slot:actions>
            <a href="{{ route('crm.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">← Pipeline</a>
            <a href="{{ route('clients.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Edit details</a>
        </x-slot:actions>
    </x-cc.header>

    {{-- ══════════ Identity strip ══════════ --}}
    <div class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-lg border border-line bg-white px-5 py-4">
        <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-navy-900 text-[15px] font-bold text-gold-400">
            @if ($client->logo_path && file_exists(public_path('storage/'.$client->logo_path)))
                <img src="{{ asset('storage/'.$client->logo_path) }}" alt="" class="h-full w-full object-cover">
            @else
                {{ $client->initials() }}
            @endif
        </span>

        <div class="min-w-0 flex-1">
            <div class="scrollbar-none flex items-center gap-x-3 overflow-hidden whitespace-nowrap text-[11.5px] text-muted">
                @if ($client->organization)<span>{{ $client->organization }}</span>@endif
                @if ($client->website)
                    <a href="{{ str_starts_with($client->website, 'http') ? $client->website : 'https://'.$client->website }}"
                       target="_blank" rel="noopener" class="transition hover:text-gold-700">{{ $client->website }}</a>
                @endif
                <span>{{ $stats['lastContact'] ? 'Last contact '.\Illuminate\Support\Carbon::parse($stats['lastContact'])->diffForHumans() : 'Never contacted' }}</span>
            </div>
        </div>
    </div>

    {{-- ══════════ What they are worth ══════════ --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-cc.kpi-tile label="Lifetime value" :value="$money($stats['lifetime'])" hint="delivered events" />
        <x-cc.kpi-tile label="In the pipeline" :value="$money($stats['openValue'])" :hint="count($openDeals).' open '.str('deal')->plural(count($openDeals))" tone="live" />
        <x-cc.kpi-tile label="Events delivered" :value="$stats['events']" hint="all time" />
        <x-cc.kpi-tile label="Win rate" :value="$stats['winRate'] === null ? '—' : $stats['winRate'].'%'" :hint="$stats['winRate'] === null ? 'nothing closed yet' : count($closedDeals).' closed'" :tone="$stats['winRate'] === null ? null : 'ok'" />
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-4">

            {{-- ══════════ People ══════════ --}}
            <section class="overflow-hidden rounded-lg border border-line bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <div>
                        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">People</p>
                        <p class="text-[11px] text-muted">Who you deal with here</p>
                    </div>
                    <button type="button" wire:click="newContact" class="rounded-full border border-line bg-white px-3 py-1.5 text-[11.5px] font-bold text-ink transition hover:border-navy-300">＋ Add contact</button>
                </div>

                <div class="divide-y divide-line">
                    @forelse ($contacts as $contact)
                        <div class="flex items-center gap-3 px-4 py-2.5">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-page text-[10.5px] font-bold text-muted">{{ $contact->initials() }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-[12.5px] font-bold text-ink">{{ $contact->name }}</span>
                                    @if ($contact->is_primary)<span class="inline-flex items-center rounded-full bg-gold-50 px-2 py-0.5 text-[10px] font-bold text-gold-700">Primary</span>@endif
                                </div>
                                <div class="scrollbar-none flex items-center gap-x-3 overflow-hidden whitespace-nowrap text-[10.5px] text-muted">
                                    @if ($contact->title)<span>{{ $contact->title }}</span>@endif
                                    @if ($contact->email)<a href="mailto:{{ $contact->email }}" class="transition hover:text-gold-700">{{ $contact->email }}</a>@endif
                                    @if ($contact->phone)<a href="tel:{{ $contact->phone }}" class="transition hover:text-gold-700">{{ $contact->phone }}</a>@endif
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                @unless ($contact->is_primary)
                                    <button type="button" wire:click="makePrimary({{ $contact->id }})" title="Make primary"
                                            class="grid h-7 w-7 place-items-center rounded-lg text-muted transition hover:bg-gold-50 hover:text-gold-700">★</button>
                                @endunless
                                <button type="button" wire:click="editContact({{ $contact->id }})" class="rounded-lg px-2.5 py-1 text-[11px] font-bold text-muted transition hover:bg-page hover:text-ink">Edit</button>
                                <x-confirm title="Remove {{ $contact->name }} from this client?"
                                           confirm="Remove" run="$wire.deleteContact({{ $contact->id }})"
                                           class="grid h-7 w-7 place-items-center rounded-lg text-muted transition hover:bg-danger-soft hover:text-danger-ink">✕</x-confirm>
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-[12px] text-muted">No one recorded here yet.</p>
                    @endforelse
                </div>
            </section>

            {{-- ══════════ Deals ══════════ --}}
            <section class="overflow-hidden rounded-lg border border-line bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-line bg-page px-4 py-3">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Deals</p>
                    <a href="{{ route('crm.index') }}" class="text-[11px] font-bold text-gold-700 transition hover:text-gold-600">Open pipeline →</a>
                </div>

                @php $dealRows = $openDeals->concat($closedDeals); @endphp
                @if ($dealRows->isEmpty())
                    <p class="px-4 py-6 text-center text-[12px] text-muted">No deals yet.</p>
                @else
                    <table class="w-full">
                        <tbody class="divide-y divide-line">
                            @foreach ($dealRows as $deal)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <span class="flex items-center gap-2">
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $deal->stageHex() }}"></span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-[12.5px] font-semibold text-ink">{{ $deal->title }}</span>
                                                <span class="block truncate text-[10.5px] text-muted">
                                                    {{ $deal->stageLabel() }}
                                                    @if ($deal->stage === 'lost' && $deal->lost_reason) · {{ $deal->lost_reason }}
                                                    @elseif ($deal->isOpen() && $deal->expected_close_on) · decides {{ $deal->expected_close_on->format('j M') }}
                                                    @endif
                                                </span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-[11.5px] font-bold tabular-nums text-ink">{{ $money($deal->value_cents, $deal->currency) }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        @if ($deal->event)
                                            <a href="{{ route('events.hub', $deal->event) }}" class="rounded-lg px-2.5 py-1 text-[11px] font-bold text-muted transition hover:bg-page hover:text-ink">Event →</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            {{-- ══════════ Events delivered ══════════ --}}
            @if ($events->isNotEmpty())
                <section class="overflow-hidden rounded-lg border border-line bg-white">
                    <div class="border-b border-line bg-page px-4 py-3"><p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Events delivered</p></div>
                    <table class="w-full">
                        <tbody class="divide-y divide-line">
                            @foreach ($events as $event)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('events.hub', $event) }}" class="block">
                                            <span class="block truncate text-[12.5px] font-semibold text-ink transition hover:text-gold-700">{{ $event->name }}</span>
                                            <span class="block truncate text-[10.5px] text-muted">
                                                {{ str($event->stage)->replace('_', ' ')->title() }} · {{ $event->starts_at?->format('M Y') ?? 'no date' }}
                                                @if ($event->archived_at) · archived @endif
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-[11.5px] font-bold tabular-nums text-ink">{{ $money((int) $event->budget_cents, $event->currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        </div>

        {{-- ══════════ Activity ══════════ --}}
        <aside class="self-start xl:sticky xl:top-4">
            <section class="overflow-hidden rounded-lg border border-line bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Activity</p>
                    <button type="button" wire:click="$toggle('showActivity')" class="rounded-full border border-line bg-white px-3 py-1.5 text-[11.5px] font-bold text-ink transition hover:border-navy-300">＋ Log</button>
                </div>

                @if ($showActivity)
                    <div class="space-y-2 border-b border-line bg-page p-4">
                        <div class="flex gap-1">
                            @foreach (\App\Support\Taxonomy::options('activity_type') as $tv => $tl)
                                <button type="button" wire:click="$set('a_type', '{{ $tv }}')"
                                        @class(['flex-1 rounded-lg border py-1.5 text-[10.5px] font-bold transition',
                                                'border-navy-900 bg-navy-900 text-white' => $a_type === $tv,
                                                'border-line bg-white text-muted hover:border-navy-300' => $a_type !== $tv])>{{ $tl }}</button>
                            @endforeach
                        </div>
                        <input type="text" wire:model="a_subject" placeholder="What happened?" class="h-9 w-full rounded-lg border border-line bg-white px-3 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                        @error('a_subject')<p class="text-[10.5px] text-danger-ink">{{ $message }}</p>@enderror
                        <textarea wire:model="a_body" rows="2" placeholder="Detail (optional)" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none"></textarea>
                        <select wire:model="a_contact_id" class="h-9 w-full rounded-lg border border-line bg-white px-3 text-[12.5px] text-ink focus:border-navy-300 focus:outline-none">
                            <option value="">— who did you speak to? —</option>
                            @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                        <label class="block">
                            <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Follow up on</span>
                            <input type="date" wire:model="a_follow_up_on" class="mt-1 h-9 w-full rounded-lg border border-line bg-white px-3 text-[12.5px] text-ink focus:border-navy-300 focus:outline-none">
                        </label>
                        <button type="button" wire:click="logActivity" class="h-9 w-full rounded-full bg-gold-500 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Save</button>
                    </div>
                @endif

                <div class="divide-y divide-line">
                    @forelse ($activities as $a)
                        <div class="px-4 py-2.5">
                            <div class="flex items-baseline gap-2">
                                <span class="inline-flex items-center rounded-full bg-page px-2 py-0.5 text-[10px] font-bold text-muted">{{ $a->typeLabel() }}</span>
                                <span class="min-w-0 flex-1 truncate text-[12px] font-semibold text-ink">{{ $a->subject }}</span>
                                <span class="shrink-0 text-[10px] tabular-nums text-muted">{{ $a->happened_at->diffForHumans(short: true) }}</span>
                            </div>
                            @if ($a->body)<p class="mt-1 text-[11px] leading-relaxed text-muted">{{ $a->body }}</p>@endif
                            <p class="mt-1 truncate text-[10px] text-muted">
                                {{ $a->user?->name ?? 'System' }}@if ($a->contact) · with {{ $a->contact->name }}@endif @if ($a->deal) · {{ $a->deal->title }}@endif
                            </p>
                            @if ($a->follow_up_on && ! $a->follow_up_done)
                                <button type="button" wire:click="completeFollowUp({{ $a->id }})"
                                        class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-warning-soft px-2 py-1 text-[10px] font-bold text-warning-ink transition hover:bg-warning-soft/60">
                                    ↻ Follow up {{ $a->follow_up_on->format('j M') }} · mark done
                                </button>
                            @endif
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-[12px] text-muted">Nothing logged with this client yet.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>

    {{-- ══════════ Contact form ══════════ --}}
    @if ($showContact)
        <x-modal :title="$editingContact ? 'Edit contact' : 'Add contact'" :subtitle="$client->name"
                 close="$set('showContact', false)" max="lg">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Name</label>
                    <input type="text" wire:model="c_name" placeholder="e.g. Layla Haddad" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                    @error('c_name')<p class="mt-1 text-[11px] text-danger-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Title</label>
                    <input type="text" wire:model="c_title" placeholder="e.g. Head of Events" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Email</label>
                    <input type="email" wire:model="c_email" placeholder="name@client.com" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                    @error('c_email')<p class="mt-1 text-[11px] text-danger-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Phone</label>
                    <input type="text" wire:model="c_phone" placeholder="+962 …" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Notes</label>
                    <textarea wire:model="c_notes" rows="2" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="Anything worth remembering."></textarea>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" wire:click="$set('showContact', false)" class="btn-sm rounded-full border border-line font-semibold text-ink transition hover:border-gold-300">Cancel</button>
                <button type="button" wire:click="saveContact" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">{{ $editingContact ? 'Save contact' : 'Add contact' }}</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
