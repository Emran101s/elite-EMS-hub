@php
    $money = fn (int $cents) => 'JD '.(abs($cents) >= 100000
        ? rtrim(rtrim(number_format($cents / 100000, 1), '0'), '.').'K'
        : number_format($cents / 100));
@endphp

<div>
    {{-- ══════════ Identity ══════════ --}}
    <div class="rounded-2xl border border-line bg-white px-4 py-3 shadow-[0_10px_26px_-20px_rgba(11,31,58,0.4)]">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
            <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-navy-900 text-[15px] font-bold text-gold-400">
                @if ($client->logo_path && file_exists(public_path('storage/'.$client->logo_path)))
                    <img src="{{ asset('storage/'.$client->logo_path) }}" alt="" class="h-full w-full object-cover">
                @else
                    {{ $client->initials() }}
                @endif
            </span>

            <div class="min-w-0 flex-1">
                <h2 class="pf truncate text-[18px] font-bold leading-tight text-navy-900">{{ $client->name }}</h2>
                <div class="scrollbar-none mt-0.5 flex items-center gap-x-3 overflow-hidden whitespace-nowrap text-[11.5px] text-muted">
                    @if ($client->organization)<span>{{ $client->organization }}</span>@endif
                    @if ($client->website)
                        <a href="{{ str_starts_with($client->website, 'http') ? $client->website : 'https://'.$client->website }}"
                           target="_blank" rel="noopener" class="transition hover:text-gold-700">{{ $client->website }}</a>
                    @endif
                    <span>{{ $stats['lastContact'] ? 'Last contact '.\Illuminate\Support\Carbon::parse($stats['lastContact'])->diffForHumans() : 'Never contacted' }}</span>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ route('crm.index') }}" class="btn-ghost btn-sm">← Pipeline</a>
                <a href="{{ route('clients.index') }}" class="btn-ghost btn-sm">Edit details</a>
            </div>
        </div>
    </div>

    {{-- ══════════ What they are worth ══════════ --}}
    @php
        $tiles = [
            ['Lifetime value', $money($stats['lifetime']), 'currency', 'delivered events'],
            ['In the pipeline', $money($stats['openValue']), 'folder', count($openDeals).' open '.str('deal')->plural(count($openDeals))],
            ['Events delivered', $stats['events'], 'calendar', 'all time'],
            ['Win rate', $stats['winRate'] === null ? '—' : $stats['winRate'].'%', 'star', $stats['winRate'] === null ? 'nothing closed yet' : count($closedDeals).' closed'],
        ];
    @endphp
    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($tiles as [$label, $value, $icon, $hint])
            <div class="rounded-[20px] border border-line bg-white p-4 shadow-[0_10px_26px_-18px_rgba(11,31,58,0.35)]">
                <div class="flex items-center gap-2">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-navy-50 text-navy-500">
                        <x-icon :name="$icon" class="h-3.5 w-3.5" />
                    </span>
                    <p class="eyebrow truncate">{{ $label }}</p>
                </div>
                <p class="pf mt-2.5 truncate text-[26px] font-bold leading-none text-navy-900">{{ $value }}</p>
                <p class="mt-2 truncate text-[11px] text-muted">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-4">

            {{-- ══════════ People ══════════ --}}
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <div>
                        <p class="eyebrow">People</p>
                        <p class="text-[11px] text-muted">Who you deal with here</p>
                    </div>
                    <button type="button" wire:click="newContact" class="btn-ghost btn-xs">＋ Add contact</button>
                </div>

                <div class="divide-y divide-line">
                    @forelse ($contacts as $contact)
                        <div class="flex items-center gap-3 px-4 py-2.5">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-navy-50 text-[10.5px] font-bold text-navy-600">{{ $contact->initials() }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-[12.5px] font-bold text-navy-900">{{ $contact->name }}</span>
                                    @if ($contact->is_primary)<span class="chip-gold">Primary</span>@endif
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
                                            class="grid h-7 w-7 place-items-center rounded-lg text-navy-300 transition hover:bg-gold-50 hover:text-gold-600">★</button>
                                @endunless
                                <button type="button" wire:click="editContact({{ $contact->id }})" class="btn-ghost btn-xs">Edit</button>
                                <button type="button" wire:click="deleteContact({{ $contact->id }})"
                                        wire:confirm="Remove {{ $contact->name }} from this client?"
                                        class="grid h-7 w-7 place-items-center rounded-lg text-navy-300 transition hover:bg-risk/10 hover:text-risk">✕</button>
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-[12px] text-muted">No one recorded here yet.</p>
                    @endforelse
                </div>
            </section>

            {{-- ══════════ Deals ══════════ --}}
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <p class="eyebrow">Deals</p>
                    <a href="{{ route('crm.index') }}" class="text-[11px] font-bold text-gold-600 transition hover:text-gold-700">Open pipeline →</a>
                </div>

                <div class="divide-y divide-line">
                    @forelse ($openDeals->concat($closedDeals) as $deal)
                        <div class="flex items-center gap-3 px-4 py-2.5">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $deal->stageHex() }}"></span>
                            <div class="min-w-0 flex-1">
                                <span class="block truncate text-[12.5px] font-semibold text-navy-900">{{ $deal->title }}</span>
                                <span class="block truncate text-[10.5px] text-muted">
                                    {{ $deal->stageLabel() }}
                                    @if ($deal->stage === 'lost' && $deal->lost_reason) · {{ $deal->lost_reason }}
                                    @elseif ($deal->isOpen() && $deal->expected_close_on) · decides {{ $deal->expected_close_on->format('d M') }}
                                    @endif
                                </span>
                            </div>
                            <span class="shrink-0 text-[11.5px] font-bold tabular-nums text-navy-700">{{ $money($deal->value_cents) }}</span>
                            @if ($deal->event)
                                <a href="{{ route('events.hub', $deal->event) }}" class="btn-ghost btn-xs shrink-0">Event →</a>
                            @endif
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-[12px] text-muted">No deals yet.</p>
                    @endforelse
                </div>
            </section>

            {{-- ══════════ Events delivered ══════════ --}}
            @if ($events->isNotEmpty())
                <section class="card overflow-hidden">
                    <div class="border-b border-line px-4 py-3"><p class="eyebrow">Events delivered</p></div>
                    <div class="divide-y divide-line">
                        @foreach ($events as $event)
                            <a href="{{ route('events.hub', $event) }}" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-navy-50">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[12.5px] font-semibold text-navy-900">{{ $event->name }}</span>
                                    <span class="block truncate text-[10.5px] text-muted">
                                        {{ str($event->stage)->replace('_', ' ')->title() }} · {{ $event->starts_at?->format('M Y') ?? 'no date' }}
                                        @if ($event->archived_at) · archived @endif
                                    </span>
                                </span>
                                <span class="shrink-0 text-[11.5px] font-bold tabular-nums text-navy-700">{{ $money((int) $event->budget_cents) }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- ══════════ Activity ══════════ --}}
        <aside class="self-start xl:sticky xl:top-[92px]">
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <p class="eyebrow">Activity</p>
                    <button type="button" wire:click="$toggle('showActivity')" class="btn-ghost btn-xs">＋ Log</button>
                </div>

                @if ($showActivity)
                    <div class="space-y-2 border-b border-line bg-page/50 p-4">
                        <div class="flex gap-1">
                            @foreach (\App\Support\Taxonomy::options('activity_type') as $tv => $tl)
                                <button type="button" wire:click="$set('a_type', '{{ $tv }}')"
                                        @class(['flex-1 rounded-lg border py-1.5 text-[10.5px] font-bold transition',
                                                'border-navy-900 bg-navy-900 text-white' => $a_type === $tv,
                                                'border-line bg-white text-navy-500 hover:border-navy-200' => $a_type !== $tv])>{{ $tl }}</button>
                            @endforeach
                        </div>
                        <input type="text" wire:model="a_subject" placeholder="What happened?" class="input h-9 text-xs">
                        @error('a_subject')<p class="text-[10.5px] text-risk">{{ $message }}</p>@enderror
                        <textarea wire:model="a_body" rows="2" placeholder="Detail (optional)" class="input text-xs"></textarea>
                        <select wire:model="a_contact_id" class="input h-9 text-xs">
                            <option value="">— who did you speak to? —</option>
                            @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                        <label class="block">
                            <span class="eyebrow">Follow up on</span>
                            <input type="date" wire:model="a_follow_up_on" class="input mt-1 h-9 text-xs">
                        </label>
                        <button type="button" wire:click="logActivity" class="btn-gold h-9 w-full !rounded-lg text-[12px]">Save</button>
                    </div>
                @endif

                <div class="divide-y divide-line">
                    @forelse ($activities as $a)
                        <div class="px-4 py-2.5">
                            <div class="flex items-baseline gap-2">
                                <span class="chip">{{ $a->typeLabel() }}</span>
                                <span class="min-w-0 flex-1 truncate text-[12px] font-semibold text-navy-900">{{ $a->subject }}</span>
                                <span class="shrink-0 text-[10px] tabular-nums text-navy-300">{{ $a->happened_at->diffForHumans(short: true) }}</span>
                            </div>
                            @if ($a->body)<p class="mt-1 text-[11px] leading-relaxed text-muted">{{ $a->body }}</p>@endif
                            <p class="mt-1 truncate text-[10px] text-navy-300">
                                {{ $a->user?->name ?? 'System' }}@if ($a->contact) · with {{ $a->contact->name }}@endif @if ($a->deal) · {{ $a->deal->title }}@endif
                            </p>
                            @if ($a->follow_up_on && ! $a->follow_up_done)
                                <button type="button" wire:click="completeFollowUp({{ $a->id }})"
                                        class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-gold-50 px-2 py-1 text-[10px] font-bold text-gold-700 ring-1 ring-gold-200 transition hover:bg-gold-100">
                                    ↻ Follow up {{ $a->follow_up_on->format('d M') }} · mark done
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
                <label class="block">
                    <span class="field-label">Name</span>
                    <input type="text" wire:model="c_name" placeholder="e.g. Layla Haddad" class="input">
                    @error('c_name')<p class="mt-1 text-[11px] text-risk">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="field-label">Title</span>
                    <input type="text" wire:model="c_title" placeholder="e.g. Head of Events" class="input">
                </label>
                <label class="block">
                    <span class="field-label">Email</span>
                    <input type="email" wire:model="c_email" placeholder="name@client.com" class="input">
                    @error('c_email')<p class="mt-1 text-[11px] text-risk">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="field-label">Phone</span>
                    <input type="text" wire:model="c_phone" placeholder="+962 …" class="input">
                </label>
                <label class="block sm:col-span-2">
                    <span class="field-label">Notes</span>
                    <textarea wire:model="c_notes" rows="2" class="input" placeholder="Anything worth remembering."></textarea>
                </label>
            </div>
            <x-slot:footer>
                <button type="button" wire:click="$set('showContact', false)" class="btn-ghost btn-sm">Cancel</button>
                <button type="button" wire:click="saveContact" class="btn-gold btn-sm">{{ $editingContact ? 'Save contact' : 'Add contact' }}</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
