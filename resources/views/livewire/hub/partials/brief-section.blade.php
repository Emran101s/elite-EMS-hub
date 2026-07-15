@php $serif = 'font-family: \'Playfair Display\', Georgia, serif;'; @endphp

@if ($type === 'kv')
    {{-- Definition list --}}
    <dl class="divide-y divide-line/70">
        @foreach ($infoFields as $fkey => $flabel)
            <div class="grid grid-cols-[130px_1fr] items-center gap-4 py-2.5">
                <dt class="text-[0.58rem] font-bold uppercase tracking-[0.12em] text-navy-400">{{ $flabel }}</dt>
                <dd><input type="text" wire:model.blur="data.event_info.{{ $fkey }}" class="w-full border-0 border-b border-transparent bg-transparent px-0 py-0.5 text-sm font-medium text-navy-900 transition focus:border-gold-400 focus:outline-none focus:ring-0"></dd>
            </div>
        @endforeach
    </dl>

@elseif ($type === 'text')
    <div class="border-l-2 border-gold-300 pl-5">
        <textarea wire:model.blur="data.{{ $key }}" rows="6" class="w-full resize-none border-0 bg-transparent p-0 text-[0.95rem] leading-relaxed text-navy-700 focus:outline-none focus:ring-0" placeholder="Write the executive summary…"></textarea>
    </div>

@elseif ($type === 'bullets')
    <ul class="space-y-1">
        @forelse ($data[$key] ?? [] as $i => $line)
            <li wire:key="{{ $key }}-{{ $i }}" class="group flex items-center gap-3">
                <span class="text-gold-500">▸</span>
                <input type="text" wire:model.blur="data.{{ $key }}.{{ $i }}" class="flex-1 border-0 border-b border-transparent bg-transparent px-0 py-1.5 text-[0.9rem] text-navy-700 transition focus:border-gold-400 focus:outline-none focus:ring-0">
                <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" class="shrink-0 text-[0.7rem] text-navy-200 opacity-0 transition hover:text-risk group-hover:opacity-100">✕</button>
            </li>
        @empty
            <li class="text-[0.8rem] italic text-navy-300">Nothing yet — use ＋ Add.</li>
        @endforelse
    </ul>

@elseif ($type === 'kpi')
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach ($data[$key] ?? [] as $i => $row)
            <div wire:key="{{ $key }}-{{ $i }}" class="group relative rounded-2xl border border-line bg-gradient-to-br from-page/60 to-white p-4 transition hover:border-gold-200 hover:shadow-sm">
                <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" class="absolute right-2 top-2 text-[0.65rem] text-navy-200 opacity-0 transition hover:text-risk group-hover:opacity-100">✕</button>
                <input type="text" wire:model.blur="data.{{ $key }}.{{ $i }}.target" class="w-full border-0 bg-transparent p-0 text-2xl font-bold text-navy-900 focus:outline-none focus:ring-0" style="{{ $serif }}">
                <input type="text" wire:model.blur="data.{{ $key }}.{{ $i }}.kpi" class="mt-1 w-full border-0 bg-transparent p-0 text-[0.6rem] font-bold uppercase tracking-[0.1em] text-navy-400 focus:outline-none focus:ring-0">
                <span class="mt-2 block h-0.5 w-8 rounded-full bg-gold-400"></span>
            </div>
        @endforeach
    </div>

@elseif ($type === 'twocol')
    <div class="space-y-1">
        <div class="grid grid-cols-[170px_1fr_28px] gap-4 px-3 pb-1 text-[0.54rem] font-bold uppercase tracking-[0.12em] text-navy-300">
            <span>{{ $twocolHeads[0] }}</span><span>{{ $twocolHeads[1] }}</span><span></span>
        </div>
        @foreach ($data[$key] ?? [] as $i => $row)
            <div wire:key="{{ $key }}-{{ $i }}" class="group grid grid-cols-[170px_1fr_28px] items-start gap-4 rounded-xl px-3 py-2.5 transition hover:bg-page/50">
                <input type="text" wire:model.blur="data.{{ $key }}.{{ $i }}.area" class="border-0 border-b border-transparent bg-transparent px-0 py-1 text-sm font-bold text-navy-900 transition focus:border-gold-400 focus:outline-none focus:ring-0">
                <textarea wire:model.blur="data.{{ $key }}.{{ $i }}.notes" rows="2" class="resize-none border-0 bg-transparent px-0 py-1 text-[0.82rem] leading-relaxed text-navy-600 focus:outline-none focus:ring-0"></textarea>
                <div class="flex flex-col items-center pt-1 opacity-0 transition group-hover:opacity-100">
                    <button type="button" wire:click="moveRow('{{ $key }}', {{ $i }}, -1)" @disabled($i === 0) class="text-[0.6rem] text-navy-300 hover:text-navy-700 disabled:opacity-20">↑</button>
                    <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" class="text-[0.7rem] text-navy-300 transition hover:text-risk">✕</button>
                </div>
            </div>
        @endforeach
    </div>

@elseif ($type === 'approval')
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ($data[$key] ?? [] as $i => $row)
            <div wire:key="{{ $key }}-{{ $i }}" class="group relative rounded-2xl border border-line bg-page/30 p-4">
                <button type="button" wire:click="removeRow('{{ $key }}', {{ $i }})" class="absolute right-2 top-2 text-[0.65rem] text-navy-200 opacity-0 transition hover:text-risk group-hover:opacity-100">✕</button>
                <input type="text" wire:model.blur="data.{{ $key }}.{{ $i }}.title" class="w-full border-0 bg-transparent p-0 text-[0.55rem] font-bold uppercase tracking-[0.12em] text-gold-600 focus:outline-none focus:ring-0" placeholder="Role / title">
                <input type="text" wire:model.blur="data.{{ $key }}.{{ $i }}.name" class="mt-1.5 w-full border-0 bg-transparent p-0 text-sm font-bold text-navy-900 focus:outline-none focus:ring-0" placeholder="Name" style="{{ $serif }}">
                <div class="mt-8 border-t border-dashed border-navy-200 pt-1.5 text-[0.5rem] uppercase tracking-wider text-navy-300">Signature &amp; date</div>
            </div>
        @endforeach
    </div>
@endif
