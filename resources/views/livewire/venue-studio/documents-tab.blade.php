<div class="rounded-lg border border-line bg-white shadow-raise p-5">
    @if (session('status'))
        <x-alert tone="ok" class="mb-3">{{ session('status') }}</x-alert>
    @endif

    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Documents</p>
    <p class="mt-1 text-[12px] text-muted">Contracts, floor plans, technical specs, insurance, permits — filed once, previewed right here, no download required.</p>

    {{-- ══ drop zone ══ --}}
    <div x-data="{ over: false }"
         @dragover.prevent="over = true"
         @dragleave.prevent="over = false"
         @drop.prevent="over = false; $refs.file.files = $event.dataTransfer.files; $refs.file.dispatchEvent(new Event('change'))"
         @click="$refs.file.click()"
         :class="over ? 'border-solid' : 'border-dashed'"
         class="relative mt-4 cursor-pointer rounded-[1.35rem] border-2 px-5 py-6 text-center shadow-eo transition"
         style="border-color: #e6e9ee;">
        <input type="file" x-ref="file" wire:model="upload" class="hidden">

        <div wire:loading wire:target="upload" class="text-xs font-semibold text-gold-700">Uploading…</div>

        <div wire:loading.remove wire:target="upload">
            @if ($upload)
                <div class="mx-auto max-w-xl text-left" @click.stop>
                    <p class="mb-2 flex items-center gap-2 text-xs font-bold text-ink">
                        <span class="rounded-md bg-gold-500-ink px-1.5 py-0.5 text-eyebrow font-black text-white">
                            {{ strtoupper(pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'FILE') }}
                        </span>
                        <span class="truncate">{{ $upload->getClientOriginalName() }}</span>
                    </p>
                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_180px_150px]">
                        <div>
                            <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !mb-1">Document name</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 text-sm" placeholder="Signed venue agreement">
                            @error('name')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !mb-1">Category</label>
                            <select wire:model="category" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-9 text-sm">
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat }}">{{ match ($cat) { 'floor_plan' => 'Floor Plan', 'tech_spec' => 'Technical Spec', default => ucfirst($cat) } }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($category === 'contract')
                            <div>
                                <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !mb-1">Status</label>
                                <select wire:model="status" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-9 text-sm">
                                    @foreach ($statuses as $s)
                                        <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" wire:click="cancel"
                                class="h-9 rounded-xl px-3 text-xs font-semibold text-muted hover:text-ink">Discard</button>
                        <x-eo.button variant="navy" wire:click="store" class="h-9 px-5 text-xs">Save document</x-eo.button>
                    </div>
                </div>
            @else
                <p class="text-xs font-semibold text-ink">
                    Drop a file here<span class="text-muted"> or click to browse</span>
                </p>
                <p class="mt-0.5 text-eyebrow text-muted">Up to 25 MB</p>
                @error('upload')<p class="mt-1.5 text-xs text-danger-ink">{{ $message }}</p>@enderror
            @endif
        </div>
    </div>

    {{-- ══ documents, grouped by category ══ --}}
    @if ($byCategory->isEmpty())
        <x-eo.empty-state title="No documents yet" icon="document" class="mt-4"
            hint="Drop a contract, floor plan, or technical spec above to file it." />
    @else
        <div class="mt-4 space-y-4">
            @foreach ($byCategory as $cat => $docs)
                <div>
                    <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-muted">{{ $docs->first()->categoryLabel() }}</p>
                    <div class="divide-y divide-line rounded-2xl border border-line">
                        @foreach ($docs as $doc)
                            <div wire:key="doc-{{ $doc->id }}" class="group/doc flex items-center gap-3 px-4 py-2.5 hover:bg-page/40">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gold-500-ink text-eyebrow font-black text-white">{{ $doc->extension() }}</span>

                                <div class="min-w-0 flex-1">
                                    <input type="text" value="{{ $doc->name }}"
                                           wire:change="rename({{ $doc->id }}, $event.target.value)"
                                           class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-7 !text-xs font-semibold">
                                    <p class="truncate px-2 text-eyebrow text-muted">
                                        {{ $doc->original_name }} · {{ $doc->sizeForHumans() }}
                                        @if ($doc->uploader) · {{ $doc->uploader->name }} @endif
                                        · {{ $doc->created_at->format('j M Y') }}
                                    </p>
                                </div>

                                @if ($doc->category === 'contract')
                                    @php [$label, $class] = \App\Models\VenueDocument::contractStatusMeta()[$doc->status] ?? [ucfirst((string) $doc->status), 'bg-page text-muted']; @endphp
                                    <select wire:change="updateStatus({{ $doc->id }}, $event.target.value)"
                                            class="shrink-0 rounded-lg border border-line bg-white px-2 py-1 text-eyebrow text-ink focus:border-gold-400 focus:outline-none">
                                        @foreach ($statuses as $s)
                                            <option value="{{ $s }}" @selected($doc->status === $s)>{{ ucfirst($s) }}</option>
                                        @endforeach
                                    </select>
                                @endif

                                <div class="flex shrink-0 items-center gap-1">
                                    @if ($doc->isViewable())
                                        <a href="{{ route('venues.documents.view', [$venue, $doc]) }}" target="_blank"
                                           class="rounded-lg bg-page px-2.5 py-1.5 text-eyebrow font-bold text-ink hover:bg-line">View</a>
                                    @endif
                                    <a href="{{ route('venues.documents.download', [$venue, $doc]) }}"
                                       class="rounded-lg bg-page px-2.5 py-1.5 text-eyebrow font-bold text-ink hover:bg-line">Download</a>
                                    <x-confirm title="Delete “{{ $doc->name }}”?"
                                               body="The file is removed for good."
                                               confirm="Delete" run="$wire.delete({{ $doc->id }})"
                                               class="rounded-lg px-1.5 py-1.5 text-eyebrow font-bold text-muted opacity-100 transition hover:bg-danger-soft hover:text-danger-ink sm:opacity-0 sm:group-hover/doc:opacity-100">✕</x-confirm>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
