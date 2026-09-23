<div x-data="{ toasts: [], push(e) { const id = Date.now() + Math.random(); this.toasts.push({ id, message: e.detail.message, tone: e.detail.tone || 'ok' }); setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 3200); } }" @approvals-toast.window="push($event)">
    {{-- toast host — the same one the event board uses --}}
    <div class="pointer-events-none fixed bottom-6 end-6 z-[60] flex flex-col gap-2" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-float" x-transition>
                <span class="h-1.5 w-1.5 rounded-full" :class="t.tone === 'ok' ? 'bg-success' : 'bg-warning'"></span><span x-text="t.message"></span>
            </div>
        </template>
    </div>

    <x-cc.header eyebrow="Task Command" title="Approvals"
                 :subtitle="$canDecide
                    ? ($waiting->isEmpty() ? 'Nothing is waiting on your decision.' : $waiting->count().' '.str('decision')->plural($waiting->count()).' waiting on you, oldest first.')
                    : 'Approvals are decided by managers — here is what you are waiting on.'" />

    @if ($canDecide)
        <div class="mt-4 inline-flex rounded-full border border-line bg-white p-1" role="tablist" aria-label="Which approvals">
            @foreach (['waiting' => ['Waiting on you', $waiting->count()], 'raised' => ['Your requests', $raised->count()]] as $key => [$label, $count])
                <button type="button" role="tab" aria-selected="{{ $show === $key ? 'true' : 'false' }}" wire:click="$set('show', '{{ $key }}')"
                        class="rounded-full px-3.5 py-1.5 text-[12px] font-bold transition {{ $show === $key ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink' }}">
                    {{ $label }} <span class="ms-1 tabular-nums {{ $show === $key ? 'text-white/70' : '' }}">{{ $count }}</span>
                </button>
            @endforeach
        </div>
    @endif

    <div class="mt-4 overflow-hidden rounded-lg border border-line bg-white">
        <ul class="divide-y divide-line">
            @forelse ($list as $approval)
                @php
                    $steps = $approval->steps;
                    $current = $approval->currentStep();
                    $index = $current ? $steps->search(fn ($s) => $s->id === $current->id) : null;
                    $decidable = $canDecide && $show !== 'raised';
                    $currency = $approval->event?->currency ?: \App\Models\CompanyProfile::currency();
                @endphp
                <li wire:key="approval-{{ $approval->id }}" class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[14px] font-semibold text-ink">{{ $approval->title }}</p>
                            <span class="rounded-full bg-warning-soft px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-warning-ink">{{ str($approval->type)->title() }}</span>
                            @if ($approval->amount_cents)
                                <span class="text-[13px] font-bold tabular-nums text-ink">{{ \App\Support\Money::forScreen($approval->amount_cents, $currency) }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-[12px] text-muted">
                            @if ($approval->event)
                                <a href="{{ route('events.hub', [$approval->event, 'tab' => 'approvals']) }}" wire:navigate class="font-semibold hover:text-ink hover:underline">{{ $approval->event->name }}</a> ·
                            @endif
                            @if ($show === 'raised')
                                with {{ $current?->assigneeLabel() ?? 'a manager' }}
                            @else
                                from {{ $approval->requester?->name ?? 'someone' }}
                            @endif
                            · waiting {{ $approval->created_at->diffForHumans(null, true) }}
                        </p>
                        @if ($steps->count() > 1 && $current)
                            <p class="mt-1 text-eyebrow font-semibold text-ink">Step {{ $index + 1 }} of {{ $steps->count() }} — {{ $current->assigneeLabel() }}</p>
                        @endif
                        @if ($approval->notes)
                            <p class="mt-1.5 text-[12.5px] text-ink">{{ $approval->notes }}</p>
                        @endif
                    </div>

                    @if ($decidable)
                        <div class="flex shrink-0 flex-wrap gap-1.5">
                            <button type="button" wire:click="decide({{ $approval->id }}, 'approved')" wire:loading.attr="disabled"
                                    class="rounded-full bg-navy-900 px-3.5 py-1.5 text-[12px] font-bold text-white transition hover:bg-navy-800">Approve</button>
                            <button type="button" wire:click="decide({{ $approval->id }}, 'needs_revision')" wire:loading.attr="disabled"
                                    class="rounded-full border border-line bg-white px-3.5 py-1.5 text-[12px] font-bold text-ink transition hover:border-warning">Request changes</button>
                            <x-confirm title="Reject “{{ $approval->title }}”?" body="The request closes and {{ $approval->requester?->name ?? 'whoever raised it' }} is told." confirm="Reject" tone="danger"
                                       run="$wire.decide({{ $approval->id }}, 'rejected')"
                                       class="rounded-full border border-line bg-white px-3.5 py-1.5 text-[12px] font-bold text-danger-ink transition hover:border-danger">Reject</x-confirm>
                        </div>
                    @endif
                </li>
            @empty
                <li class="px-4 py-10 text-center text-[13px] text-muted">
                    @if ($show === 'raised' || ! $canDecide)
                        You have no requests waiting on anyone.
                    @else
                        Nothing is waiting on your decision.
                    @endif
                </li>
            @endforelse
        </ul>
    </div>
</div>
