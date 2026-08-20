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

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="Commercial Command"
        title="{{ $client->name }}"
        subtitle="Client record — people, deals, events and every conversation."
    >
        <x-slot:actions>
            <x-eo.button variant="ghost" size="sm" href="{{ route('crm.index') }}">← Pipeline</x-eo.button>
            <x-eo.button variant="ghost" size="sm" href="{{ route('clients.index') }}">Edit details</x-eo.button>
        </x-slot:actions>
    </x-eo.page-header>

    {{-- ══════════ Identity strip ══════════ --}}
    <div class="eo-soft-card flex flex-wrap items-center gap-x-5 gap-y-3 px-5 py-4">
        <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-eo-navy text-[15px] font-bold text-eo-gold">
            @if ($client->logo_path && file_exists(public_path('storage/'.$client->logo_path)))
                <img src="{{ asset('storage/'.$client->logo_path) }}" alt="" class="h-full w-full object-cover">
            @else
                {{ $client->initials() }}
            @endif
        </span>

        <div class="min-w-0 flex-1">
            <div class="scrollbar-none flex items-center gap-x-3 overflow-hidden whitespace-nowrap text-[11.5px] text-eo-muted">
                @if ($client->organization)<span>{{ $client->organization }}</span>@endif
                @if ($client->website)
                    <a href="{{ str_starts_with($client->website, 'http') ? $client->website : 'https://'.$client->website }}"
                       target="_blank" rel="noopener" class="transition hover:text-eo-teal-ink">{{ $client->website }}</a>
                @endif
                <span>{{ $stats['lastContact'] ? 'Last contact '.\Illuminate\Support\Carbon::parse($stats['lastContact'])->diffForHumans() : 'Never contacted' }}</span>
            </div>
        </div>
    </div>

    {{-- ══════════ What they are worth ══════════ --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-eo.metric-pill label="Lifetime value" :value="$money($stats['lifetime'])" hint="delivered events" />
        <x-eo.metric-pill label="In the pipeline" :value="$money($stats['openValue'])" :hint="count($openDeals).' open '.str('deal')->plural(count($openDeals))" tone="live" />
        <x-eo.metric-pill label="Events delivered" :value="$stats['events']" hint="all time" />
        <x-eo.metric-pill label="Win rate" :value="$stats['winRate'] === null ? '—' : $stats['winRate'].'%'" :hint="$stats['winRate'] === null ? 'nothing closed yet' : count($closedDeals).' closed'" :tone="$stats['winRate'] === null ? null : 'ok'" />
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-4">

            {{-- ══════════ People ══════════ --}}
            <section class="eo-soft-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-eo-line px-4 py-3">
                    <div>
                        <p class="eo-label">People</p>
                        <p class="text-[11px] text-eo-muted">Who you deal with here</p>
                    </div>
                    <button type="button" wire:click="newContact" class="eo-btn-ghost eo-btn-sm">＋ Add contact</button>
                </div>

                <div class="divide-y divide-eo-line">
                    @forelse ($contacts as $contact)
                        <div class="flex items-center gap-3 px-4 py-2.5">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-eo-bg text-[10.5px] font-bold text-eo-muted">{{ $contact->initials() }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-[12.5px] font-bold text-eo-text">{{ $contact->name }}</span>
                                    @if ($contact->is_primary)<x-eo.status-pill tone="premium">Primary</x-eo.status-pill>@endif
                                </div>
                                <div class="scrollbar-none flex items-center gap-x-3 overflow-hidden whitespace-nowrap text-[10.5px] text-eo-muted">
                                    @if ($contact->title)<span>{{ $contact->title }}</span>@endif
                                    @if ($contact->email)<a href="mailto:{{ $contact->email }}" class="transition hover:text-eo-teal-ink">{{ $contact->email }}</a>@endif
                                    @if ($contact->phone)<a href="tel:{{ $contact->phone }}" class="transition hover:text-eo-teal-ink">{{ $contact->phone }}</a>@endif
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                @unless ($contact->is_primary)
                                    <button type="button" wire:click="makePrimary({{ $contact->id }})" title="Make primary"
                                            class="grid h-7 w-7 place-items-center rounded-lg text-eo-muted transition hover:bg-eo-gold-soft/30 hover:text-eo-gold-ink">★</button>
                                @endunless
                                <button type="button" wire:click="editContact({{ $contact->id }})" class="eo-btn-ghost eo-btn-sm !px-2.5 !py-1 !text-[11px]">Edit</button>
                                <x-confirm title="Remove {{ $contact->name }} from this client?"
                                           confirm="Remove" run="$wire.deleteContact({{ $contact->id }})"
                                           class="grid h-7 w-7 place-items-center rounded-lg text-eo-muted transition hover:bg-eo-risk/10 hover:text-eo-risk">✕</x-confirm>
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-[12px] text-eo-muted">No one recorded here yet.</p>
                    @endforelse
                </div>
            </section>

            {{-- ══════════ Deals ══════════ --}}
            <section class="eo-table-wrap">
                <div class="flex items-center justify-between gap-3 border-b border-eo-line bg-eo-workspace px-4 py-3">
                    <p class="eo-label">Deals</p>
                    <a href="{{ route('crm.index') }}" class="text-[11px] font-bold text-eo-teal-ink transition hover:text-eo-teal-deep">Open pipeline →</a>
                </div>

                @php $dealRows = $openDeals->concat($closedDeals); @endphp
                @if ($dealRows->isEmpty())
                    <p class="px-4 py-6 text-center text-[12px] text-eo-muted">No deals yet.</p>
                @else
                    <table class="eo-table w-full">
                        <tbody>
                            @foreach ($dealRows as $deal)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <span class="flex items-center gap-2">
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $deal->stageHex() }}"></span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-[12.5px] font-semibold text-eo-text">{{ $deal->title }}</span>
                                                <span class="block truncate text-[10.5px] text-eo-muted">
                                                    {{ $deal->stageLabel() }}
                                                    @if ($deal->stage === 'lost' && $deal->lost_reason) · {{ $deal->lost_reason }}
                                                    @elseif ($deal->isOpen() && $deal->expected_close_on) · decides {{ $deal->expected_close_on->format('j M') }}
                                                    @endif
                                                </span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-[11.5px] font-bold tabular-nums text-eo-text">{{ $money($deal->value_cents, $deal->currency) }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        @if ($deal->event)
                                            <a href="{{ route('events.hub', $deal->event) }}" class="eo-btn-ghost eo-btn-sm !px-2.5 !py-1 !text-[11px]">Event →</a>
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
                <section class="eo-table-wrap">
                    <div class="border-b border-eo-line bg-eo-workspace px-4 py-3"><p class="eo-label">Events delivered</p></div>
                    <table class="eo-table w-full">
                        <tbody>
                            @foreach ($events as $event)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('events.hub', $event) }}" class="block">
                                            <span class="block truncate text-[12.5px] font-semibold text-eo-text transition hover:text-eo-teal-ink">{{ $event->name }}</span>
                                            <span class="block truncate text-[10.5px] text-eo-muted">
                                                {{ str($event->stage)->replace('_', ' ')->title() }} · {{ $event->starts_at?->format('M Y') ?? 'no date' }}
                                                @if ($event->archived_at) · archived @endif
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-[11.5px] font-bold tabular-nums text-eo-text">{{ $money((int) $event->budget_cents, $event->currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        </div>

        {{-- ══════════ Activity ══════════ --}}
        <aside class="self-start xl:sticky xl:top-4">
            <section class="eo-soft-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-eo-line px-4 py-3">
                    <p class="eo-label">Activity</p>
                    <button type="button" wire:click="$toggle('showActivity')" class="eo-btn-ghost eo-btn-sm">＋ Log</button>
                </div>

                @if ($showActivity)
                    <div class="space-y-2 border-b border-eo-line bg-eo-workspace p-4">
                        <div class="flex gap-1">
                            @foreach (\App\Support\Taxonomy::options('activity_type') as $tv => $tl)
                                <button type="button" wire:click="$set('a_type', '{{ $tv }}')"
                                        @class(['flex-1 rounded-lg border py-1.5 text-[10.5px] font-bold transition',
                                                'border-eo-navy bg-eo-navy text-white' => $a_type === $tv,
                                                'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30' => $a_type !== $tv])>{{ $tl }}</button>
                            @endforeach
                        </div>
                        <input type="text" wire:model="a_subject" placeholder="What happened?" class="eo-input h-9 text-xs">
                        @error('a_subject')<p class="text-[10.5px] text-eo-risk-ink">{{ $message }}</p>@enderror
                        <textarea wire:model="a_body" rows="2" placeholder="Detail (optional)" class="eo-textarea text-xs"></textarea>
                        <select wire:model="a_contact_id" class="eo-select h-9 text-xs">
                            <option value="">— who did you speak to? —</option>
                            @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                        <label class="block">
                            <span class="eo-label">Follow up on</span>
                            <input type="date" wire:model="a_follow_up_on" class="eo-input mt-1 h-9 text-xs">
                        </label>
                        <x-eo.button size="sm" wire:click="logActivity" class="h-9 w-full justify-center">Save</x-eo.button>
                    </div>
                @endif

                <div class="divide-y divide-eo-line">
                    @forelse ($activities as $a)
                        <div class="px-4 py-2.5">
                            <div class="flex items-baseline gap-2">
                                <x-eo.status-pill tone="pending">{{ $a->typeLabel() }}</x-eo.status-pill>
                                <span class="min-w-0 flex-1 truncate text-[12px] font-semibold text-eo-text">{{ $a->subject }}</span>
                                <span class="shrink-0 text-[10px] tabular-nums text-eo-muted">{{ $a->happened_at->diffForHumans(short: true) }}</span>
                            </div>
                            @if ($a->body)<p class="mt-1 text-[11px] leading-relaxed text-eo-muted">{{ $a->body }}</p>@endif
                            <p class="mt-1 truncate text-[10px] text-eo-muted">
                                {{ $a->user?->name ?? 'System' }}@if ($a->contact) · with {{ $a->contact->name }}@endif @if ($a->deal) · {{ $a->deal->title }}@endif
                            </p>
                            @if ($a->follow_up_on && ! $a->follow_up_done)
                                <button type="button" wire:click="completeFollowUp({{ $a->id }})"
                                        class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-eo-warn-soft px-2 py-1 text-[10px] font-bold text-eo-warn-ink ring-1 ring-eo-warn/20 transition hover:bg-eo-warn-soft/60">
                                    ↻ Follow up {{ $a->follow_up_on->format('j M') }} · mark done
                                </button>
                            @endif
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-[12px] text-eo-muted">Nothing logged with this client yet.</p>
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
                    <label class="eo-label mb-1">Name</label>
                    <input type="text" wire:model="c_name" placeholder="e.g. Layla Haddad" class="eo-input">
                    @error('c_name')<p class="mt-1 text-[11px] text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="eo-label mb-1">Title</label>
                    <input type="text" wire:model="c_title" placeholder="e.g. Head of Events" class="eo-input">
                </div>
                <div>
                    <label class="eo-label mb-1">Email</label>
                    <input type="email" wire:model="c_email" placeholder="name@client.com" class="eo-input">
                    @error('c_email')<p class="mt-1 text-[11px] text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="eo-label mb-1">Phone</label>
                    <input type="text" wire:model="c_phone" placeholder="+962 …" class="eo-input">
                </div>
                <div class="sm:col-span-2">
                    <label class="eo-label mb-1">Notes</label>
                    <textarea wire:model="c_notes" rows="2" class="eo-textarea" placeholder="Anything worth remembering."></textarea>
                </div>
            </div>
            <x-slot:footer>
                <button type="button" wire:click="$set('showContact', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                <x-eo.button size="sm" wire:click="saveContact">{{ $editingContact ? 'Save contact' : 'Add contact' }}</x-eo.button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
