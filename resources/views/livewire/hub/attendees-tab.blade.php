<div>
    {{-- ══ Stat cards ══ --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-4">
            <p class="text-[0.6rem] font-bold uppercase tracking-wide text-muted">Registered</p>
            <p class="mt-0.5 text-2xl font-black text-navy-900">{{ number_format($stats['registered']) }}@if ($stats['capacity'])<span class="text-sm font-semibold text-muted"> / {{ number_format($stats['capacity']) }}</span>@endif</p>
            @if ($stats['fillPct'] !== null)
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-page"><div class="h-full rounded-full bg-gold-400" style="width: {{ $stats['fillPct'] }}%"></div></div>
                <p class="mt-1 text-[0.6rem] text-muted">{{ $stats['fillPct'] }}% of capacity</p>
            @endif
        </div>
        <div class="card p-4">
            <p class="text-[0.6rem] font-bold uppercase tracking-wide text-muted">Checked in</p>
            <p class="mt-0.5 text-2xl font-black text-emerald-600">{{ number_format($stats['checkedIn']) }}</p>
            <p class="mt-1 text-[0.6rem] text-muted">{{ number_format($stats['confirmed']) }} confirmed</p>
        </div>
        <div class="card p-4">
            <p class="text-[0.6rem] font-bold uppercase tracking-wide text-muted">VIPs</p>
            <p class="mt-0.5 text-2xl font-black text-gold-600">{{ number_format($stats['vips']) }}</p>
            <p class="mt-1 text-[0.6rem] text-muted">{{ number_format($stats['cancelled']) }} cancelled</p>
        </div>
        <div class="card p-4">
            <p class="text-[0.6rem] font-bold uppercase tracking-wide text-muted">Ticket revenue</p>
            <p class="mt-0.5 text-2xl font-black text-navy-900">{{ $event->money($stats['revenue']) }}</p>
            <p class="mt-1 text-[0.6rem] text-muted">across paid tickets</p>
        </div>
    </div>

    {{-- ══ Ticket-type breakdown ══ --}}
    @if ($byTicket->isNotEmpty())
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($byTicket as $type => $count)
                <button type="button" wire:click="$set('filterTicket', '{{ $filterTicket === $type ? '' : $type }}')"
                        class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $filterTicket === $type ? 'border-navy-900 bg-navy-900 text-white' : 'border-line bg-white text-navy-700 hover:border-gold-300' }}">
                    {{ $type }} <span class="rounded-full {{ $filterTicket === $type ? 'bg-white/20' : 'bg-navy-100 text-navy-600' }} px-1.5 text-[0.6rem]">{{ $count }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- ══ Toolbar ══ --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, email, org…" class="input h-10 w-56 text-sm">
        <select wire:model.live="filterStatus" class="input h-10 w-40 text-sm">
            <option value="">All statuses</option>
            @foreach (\App\Models\EventAttendee::STATUS_META as $val => $meta)<option value="{{ $val }}">{{ $meta[0] }}</option>@endforeach
        </select>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="$toggle('showImport')" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-xs font-semibold text-navy-700 transition hover:border-gold-300">⇪ Import</button>
            <button type="button" wire:click="newItem" class="btn-navy h-10 gap-1.5 px-4 text-xs"><span class="text-gold-400">＋</span> Add attendee</button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($showImport)
        <form wire:submit="import" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="flex-1">
                <label class="field-label !mb-1 !text-[0.62rem]" for="att-import">Excel (.xlsx) or CSV — columns: <span class="font-semibold text-navy-700">name</span>, email, organization, phone, ticket, amount</label>
                <input id="att-import" type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv" class="input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                @error('importFile') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
            <button type="submit" class="btn-navy h-10 px-5 text-xs" wire:loading.attr="disabled" wire:target="import,importFile">Import</button>
        </form>
    @endif

    {{-- ══ Table ══ --}}
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[760px]">
            <thead>
                <tr class="border-b border-line bg-page/40 text-left text-[0.6rem] font-bold uppercase tracking-wide text-muted">
                    <th class="px-4 py-2.5">Attendee</th>
                    <th class="px-3 py-2.5">Ticket</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5 text-right">Fee</th>
                    <th class="px-3 py-2.5 text-center">Check-in</th>
                    <th class="px-3 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attendees as $a)
                    @php [$sl, $sc] = $a->statusMeta(); @endphp
                    <tr wire:key="att-{{ $a->id }}" wire:click="edit({{ $a->id }})" class="group cursor-pointer border-b border-line last:border-0 hover:bg-page/30">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-900 text-[0.62rem] font-bold text-gold-400">{{ $a->initials() }}</span>
                                <div class="min-w-0">
                                    <p class="flex items-center gap-1.5 truncate text-sm font-bold text-navy-900">{{ $a->name }}@if ($a->vip)<span class="text-gold-500" title="VIP">★</span>@endif</p>
                                    <p class="truncate text-[0.68rem] text-muted">{{ $a->organization ?: $a->email ?: '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2.5"><span class="rounded-full bg-navy-50 px-2.5 py-0.5 text-[0.6rem] font-bold text-navy-600">{{ $a->ticket_type }}</span></td>
                        <td class="px-3 py-2.5"><span class="rounded-full px-2.5 py-0.5 text-[0.6rem] font-bold {{ $sc }}">{{ $sl }}</span></td>
                        <td class="px-3 py-2.5 text-right text-xs font-semibold text-navy-700">{{ $a->amount_cents ? $event->money($a->amount_cents) : '—' }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <button type="button" wire:click.stop="toggleCheckIn({{ $a->id }})"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-sm transition {{ $a->status === 'checked_in' ? 'bg-emerald-100 text-emerald-700' : 'bg-navy-50 text-navy-300 hover:bg-navy-100 hover:text-navy-600' }}"
                                    title="{{ $a->status === 'checked_in' ? 'Undo check-in' : 'Check in' }}">✓</button>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <span class="text-[0.6rem] font-semibold text-navy-400 opacity-0 transition group-hover:opacity-100">Edit ✎</span>
                                <button type="button" wire:click.stop="delete({{ $a->id }})" wire:confirm="Remove {{ $a->name }}?" class="rounded-lg bg-risk/10 px-2 py-1 text-[0.6rem] font-bold text-red-700 opacity-0 transition hover:bg-risk/20 group-hover:opacity-100">✕</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-16 text-center">
                        <p class="text-sm font-semibold text-navy-900">{{ $search || $filterStatus || $filterTicket ? 'No attendees match your filters' : 'No attendees yet' }}</p>
                        @unless ($search || $filterStatus || $filterTicket)
                            <p class="mx-auto mt-1 max-w-md text-xs text-muted">Add attendees one by one, or import your registration list from Excel.</p>
                            <div class="mt-4 flex justify-center gap-2">
                                <button type="button" wire:click="newItem" class="btn-navy h-10 px-5 text-xs">＋ Add attendee</button>
                                <button type="button" wire:click="$set('showImport', true)" class="h-10 rounded-xl border border-line bg-white px-4 text-xs font-semibold text-navy-700 hover:border-gold-300">⇪ Import list</button>
                            </div>
                        @endunless
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-center text-[0.62rem] text-muted">{{ $attendees->count() }} shown</p>

    {{-- ══ Modal ══ --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-950/40 p-4 py-10 backdrop-blur-sm">
            <div class="card w-full max-w-lg p-6 shadow-2xl" @click.outside="$wire.set('showForm', false)">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="pf text-base font-bold text-navy-900">{{ $editingId ? 'Edit attendee' : 'Add attendee' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="text-navy-400 hover:text-navy-900">✕</button>
                </div>
                <form wire:submit="save" class="grid gap-3.5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Full name</label>
                            <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="e.g. Layla Haddad">
                            @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Email</label>
                            <input type="email" wire:model="email" class="input h-10 text-sm" placeholder="name@company.com">
                            @error('email')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Organization</label>
                            <input type="text" wire:model="organization" class="input h-10 text-sm">
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Phone</label>
                            <input type="text" wire:model="phone" class="input h-10 text-sm">
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Ticket type</label>
                            <select wire:model="ticket_type" class="input h-10 text-sm">
                                @foreach ($ticketTypes as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Status</label>
                            <select wire:model="status" class="input h-10 text-sm">
                                @foreach (\App\Models\EventAttendee::STATUS_META as $val => $meta)<option value="{{ $val }}">{{ $meta[0] }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Fee ({{ $event->currencySymbol() }})</label>
                            <input type="number" step="0.01" min="0" wire:model="amount" class="input h-10 text-sm" placeholder="0">
                            @error('amount')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-xs font-semibold text-navy-700"><input type="checkbox" wire:model="vip" class="h-4 w-4 rounded border-line text-gold-500 focus:ring-gold-400"> ★ VIP</label>
                        <div>
                            <label class="field-label !mb-1 !text-[0.62rem]">Dietary / access</label>
                            <input type="text" wire:model="dietary" class="input h-9 text-sm" placeholder="e.g. Vegetarian, wheelchair">
                        </div>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-[0.62rem]">Notes</label>
                        <textarea wire:model="notes" rows="2" class="input text-sm"></textarea>
                    </div>
                    <div class="mt-1 flex items-center gap-3">
                        @if ($editingId)
                            <button type="button" wire:click="delete({{ $editingId }})" wire:confirm="Remove this attendee?" class="rounded-xl px-3 py-2 text-xs font-bold text-risk transition hover:bg-risk/5">Delete</button>
                        @endif
                        <div class="ml-auto flex gap-2">
                            <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                            <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Save' : 'Add attendee' }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-bulk-bar :count="$this->selectedCount()" noun="attendee" />
</div>
