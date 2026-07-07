<div>
    @php
        $estimated = $items->sum('estimated_cents');
        $actual = $items->sum('actual_cents');
        $paid = $items->where('payment_status', 'paid')->sum('actual_cents');
        $fmt = fn (int $cents) => '$'.number_format($cents / 100);
    @endphp

    <div class="mb-4 flex items-center justify-between">
        <div class="flex flex-wrap gap-3">
            @foreach ([
                ['label' => 'Event Budget', 'value' => $fmt($event->budget_cents)],
                ['label' => 'Estimated', 'value' => $fmt($estimated)],
                ['label' => 'Actual', 'value' => $fmt($actual), 'risk' => $actual > $estimated],
                ['label' => 'Paid', 'value' => $fmt($paid)],
                ['label' => 'Outstanding', 'value' => $fmt(max($actual - $paid, 0))],
            ] as $stat)
                <div class="flex h-[90px] w-[155px] flex-col justify-center rounded-[18px] border border-line bg-white px-4 shadow-[0_10px_30px_rgba(15,23,42,0.05)]">
                    <p class="text-[11px] font-semibold text-muted">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-xl font-bold {{ ($stat['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>
        <button type="button" wire:click="$toggle('showForm')" class="btn-gold h-10 shrink-0 px-4 text-xs">＋ Add Line</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card mb-5 grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="field-label !mb-1 !text-[0.62rem]" for="b-cat">Category</label>
                <select id="b-cat" wire:model="category" class="input h-10 text-sm">
                    @foreach (\App\Models\EventBudgetItem::CATEGORIES as $categoryOption)<option value="{{ $categoryOption }}">{{ str($categoryOption)->replace('_', ' & ')->title() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label !mb-1 !text-[0.62rem]" for="b-desc">Description</label>
                <input id="b-desc" type="text" wire:model="description" class="input h-10 text-sm" placeholder="e.g. Main stage build">
            </div>
            <div>
                <label class="field-label !mb-1 !text-[0.62rem]" for="b-est">Estimated (USD)</label>
                <input id="b-est" type="number" step="0.01" min="0" wire:model="estimated" class="input h-10 text-sm" placeholder="25000">
                @error('estimated') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label !mb-1 !text-[0.62rem]" for="b-act">Actual (USD)</label>
                <input id="b-act" type="number" step="0.01" min="0" wire:model="actual" class="input h-10 text-sm" placeholder="0">
            </div>
            <div>
                <label class="field-label !mb-1 !text-[0.62rem]" for="b-sup">Supplier</label>
                <select id="b-sup" wire:model="supplier_id" class="input h-10 text-sm">
                    <option value="">—</option>
                    @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label !mb-1 !text-[0.62rem]" for="b-pay">Payment status</label>
                <select id="b-pay" wire:model="payment_status" class="input h-10 text-sm">
                    @foreach (\App\Models\EventBudgetItem::PAYMENT_STATUSES as $paymentOption)<option value="{{ $paymentOption }}">{{ str($paymentOption)->title() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label !mb-1 !text-[0.62rem]" for="b-inv">Invoice #</label>
                <input id="b-inv" type="text" wire:model="invoice_number" class="input h-10 text-sm" placeholder="INV-2026-060">
            </div>
            <div>
                <label class="field-label !mb-1 !text-[0.62rem]" for="b-due">Due date</label>
                <input id="b-due" type="date" wire:model="due_on" class="input h-10 text-sm">
            </div>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                <button type="submit" class="btn-navy h-10 px-5 text-xs">Save Line</button>
            </div>
        </form>
    @endif

    <div class="card divide-y divide-line">
        <div class="hidden grid-cols-12 gap-3 px-6 py-3 text-[0.65rem] font-semibold uppercase tracking-wide text-muted md:grid">
            <span class="col-span-4">Line Item</span>
            <span class="col-span-2 text-right">Estimated</span>
            <span class="col-span-2 text-right">Actual</span>
            <span class="col-span-2">Supplier</span>
            <span class="col-span-2 text-right">Payment</span>
        </div>
        @forelse ($items as $item)
            <div class="group/line grid grid-cols-2 items-center gap-3 px-6 py-4 md:grid-cols-12">
                <div class="col-span-2 md:col-span-4">
                    <p class="text-sm font-semibold text-navy-900">{{ $item->description ?? str($item->category)->title() }}</p>
                    <p class="text-[0.65rem] uppercase tracking-wide text-muted">{{ str($item->category)->replace('_', ' & ')->title() }}
                        @if ($item->invoice_number) · {{ $item->invoice_number }} @endif
                    </p>
                </div>
                <p class="text-xs font-semibold text-navy-900 md:col-span-2 md:text-right">{{ $item->estimated_cents ? '$'.number_format($item->estimated_cents / 100) : '—' }}</p>
                <p class="text-xs font-semibold md:col-span-2 md:text-right {{ $item->actual_cents > $item->estimated_cents ? 'text-risk' : 'text-navy-900' }}">
                    {{ $item->actual_cents ? '$'.number_format($item->actual_cents / 100) : '—' }}
                </p>
                <p class="truncate text-xs text-muted md:col-span-2">{{ $item->supplier?->name ?? '—' }}</p>
                <div class="flex items-center justify-end gap-2 md:col-span-2">
                    <x-status-badge :status="$item->payment_status" />
                    @if ($item->payment_status !== 'paid')
                        <button type="button" wire:click="setPayment({{ $item->id }}, 'paid')"
                                class="rounded-lg bg-track/10 px-2 py-1 text-[0.6rem] font-bold text-emerald-700 opacity-0 transition hover:bg-track/20 group-hover/line:opacity-100">✓ Mark Paid</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-6 py-12 text-center text-sm text-muted">No budget lines yet — add estimates to activate Budget Health.</p>
        @endforelse
    </div>
</div>
