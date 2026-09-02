{{-- 'sourced': the Scope of Work, written on the Scope tab and read here.
     Deliberately not editable in the brief — one authored home, one copy. --}}
@if ($type === 'sourced')
    @php $scope = $event->scopeItems; @endphp

    @if ($scope->isEmpty())
        <p class="text-[13px] text-muted">
            Nothing has been written on the <a href="{{ route('events.hub', [$event, 'tab' => 'scope']) }}" class="font-semibold text-gold-700 hover:underline">Scope tab</a> yet.
            The scope of work lives there, and this section reads it.
        </p>
    @else
        <div class="space-y-3">
            @foreach ($scope->where('is_exclusion', false)->groupBy('type') as $key => $rows)
                <div>
                    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ $rows->first()->typeLabel() }}</p>
                    <ul class="mt-1 space-y-1.5">
                        @foreach ($rows as $item)
                            <li class="text-[13px] leading-snug text-ink">
                                <span class="font-semibold">{{ $item->title }}</span>
                                @if ($item->body)<span class="block text-[12px] text-muted">{{ $item->body }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            @if ($scope->where('is_exclusion', true)->isNotEmpty())
                <div class="rounded-lg px-3 py-2" style="background: var(--color-warning-soft)">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.12em]" style="color: var(--color-warning-ink)">Not included</p>
                    <ul class="mt-1 space-y-1">
                        @foreach ($scope->where('is_exclusion', true) as $item)
                            <li class="text-[12.5px] leading-snug text-ink">
                                <span class="font-semibold">{{ $item->title }}</span>@if ($item->body) — <span class="text-muted">{{ $item->body }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <p class="mt-3 text-[11px] text-muted">
            Written on the <a href="{{ route('events.hub', [$event, 'tab' => 'scope']) }}" class="font-semibold text-gold-700 hover:underline">Scope tab</a> — revise it there and this follows.
        </p>
    @endif

@elseif ($type === 'kv')
    {{-- Definition list --}}
    <dl class="divide-y divide-line/70">
        @foreach ($infoFields as $fkey => $flabel)
            <div class="grid grid-cols-[130px_1fr] items-center gap-4 py-2.5">
                <dt class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ $flabel }}</dt>
                <dd><input type="text" wire:model.live.debounce.500ms="data.event_info.{{ $fkey }}" class="w-full border-0 border-b border-transparent bg-transparent px-0 py-0.5 text-sm font-medium text-ink transition focus:border-navy-300 focus:outline-none focus:ring-0"></dd>
            </div>
        @endforeach
    </dl>

@elseif ($type === 'text')
    <div class="border-l-2 border-gold-300 pl-5">
        <textarea wire:model.live.debounce.500ms="data.{{ $key }}" rows="6" class="w-full resize-none border-0 bg-transparent p-0 text-base leading-relaxed text-ink focus:outline-none focus:ring-0" placeholder="Write the executive summary…"></textarea>
    </div>

@elseif ($type === 'bullets')
    <ul class="space-y-1">
        @forelse ($data[$key] ?? [] as $i => $line)
            <li wire:key="{{ $key }}-{{ $i }}" class="group flex items-center gap-3">
                <span class="text-gold-700">▸</span>
                <input type="text" wire:model.live.debounce.500ms="data.{{ $key }}.{{ $i }}" class="flex-1 border-0 border-b border-transparent bg-transparent px-0 py-1.5 text-sm text-ink transition focus:border-navy-300 focus:outline-none focus:ring-0">
                <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" class="shrink-0 text-micro text-muted opacity-100 transition sm:opacity-0 hover:text-danger-ink sm:group-hover:opacity-100">✕</button>
            </li>
        @empty
            <li class="text-sm italic text-muted">Nothing yet — use ＋ Add.</li>
        @endforelse
    </ul>

@elseif ($type === 'kpi')
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach ($data[$key] ?? [] as $i => $row)
            <div wire:key="{{ $key }}-{{ $i }}" class="group relative rounded-lg border border-line bg-gradient-to-br from-page to-white p-4 transition hover:border-navy-300 hover:shadow-float">
                <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" class="absolute right-2 top-2 text-eyebrow text-muted opacity-100 transition sm:opacity-0 hover:text-danger-ink sm:group-hover:opacity-100">✕</button>
                <input type="text" wire:model.live.debounce.500ms="data.{{ $key }}.{{ $i }}.target" class="w-full border-0 bg-transparent p-0 text-2xl font-bold text-ink focus:outline-none focus:ring-0">
                <input type="text" wire:model.live.debounce.500ms="data.{{ $key }}.{{ $i }}.kpi" class="mt-1 w-full border-0 bg-transparent p-0 text-eyebrow font-bold uppercase tracking-[0.1em] text-muted focus:outline-none focus:ring-0">
                <span class="mt-2 block h-0.5 w-8 rounded-full bg-gold-500"></span>
            </div>
        @endforeach
    </div>

@elseif ($type === 'twocol')
    <div class="space-y-1">
        <div class="grid grid-cols-[170px_1fr_28px] gap-4 px-3 pb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">
            <span>{{ $twocolHeads[0] }}</span><span>{{ $twocolHeads[1] }}</span><span></span>
        </div>
        @foreach ($data[$key] ?? [] as $i => $row)
            <div wire:key="{{ $key }}-{{ $i }}" class="group grid grid-cols-[170px_1fr_28px] items-start gap-4 rounded-lg px-3 py-2.5 transition hover:bg-page">
                <input type="text" wire:model.live.debounce.500ms="data.{{ $key }}.{{ $i }}.area" class="border-0 border-b border-transparent bg-transparent px-0 py-1 text-sm font-bold text-ink transition focus:border-navy-300 focus:outline-none focus:ring-0">
                <textarea wire:model.live.debounce.500ms="data.{{ $key }}.{{ $i }}.notes" rows="2" class="resize-none border-0 bg-transparent px-0 py-1 text-sm leading-relaxed text-ink/80 focus:outline-none focus:ring-0"></textarea>
                <div class="flex flex-col items-center pt-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                    <button type="button" wire:click="moveRow('{{ $key }}', {{ $i }}, -1)" @disabled($i === 0) class="text-eyebrow text-muted hover:text-ink disabled:opacity-20">↑</button>
                    <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" class="text-micro text-muted transition hover:text-danger-ink">✕</button>
                </div>
            </div>
        @endforeach
    </div>

@elseif ($type === 'approval')
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ($data[$key] ?? [] as $i => $row)
            <div wire:key="{{ $key }}-{{ $i }}" class="group relative rounded-lg border border-line bg-page p-4">
                <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" class="absolute right-2 top-2 text-eyebrow text-muted opacity-100 transition sm:opacity-0 hover:text-danger-ink sm:group-hover:opacity-100">✕</button>
                <input type="text" wire:model.live.debounce.500ms="data.{{ $key }}.{{ $i }}.title" class="w-full border-0 bg-transparent p-0 text-eyebrow font-bold uppercase tracking-[0.12em] text-gold-700 focus:outline-none focus:ring-0" placeholder="Role / title">
                <input type="text" wire:model.live.debounce.500ms="data.{{ $key }}.{{ $i }}.name" class="mt-1.5 w-full border-0 bg-transparent p-0 text-sm font-bold text-ink focus:outline-none focus:ring-0" placeholder="Name">
                <div class="mt-8 border-t border-dashed border-line pt-1.5 text-eyebrow uppercase tracking-wider text-muted">Signature &amp; date</div>
            </div>
        @endforeach
    </div>
@endif
