{{--
    The drawer body, shared by both presentations:
      · the Documents tab's full-width library
      · the right-edge dock that rides along on every module tab
    `$dock` narrows the grids; everything else is identical.
--}}
    {{-- ══ header ══ --}}
    <div class="flex flex-wrap items-center gap-3 border-b px-5 py-3.5"
         style="background: linear-gradient(135deg,
                    color-mix(in srgb, {{ $color }} 17%, transparent),
                    color-mix(in srgb, {{ $color }} 7%, transparent) 65%,
                    color-mix(in srgb, {{ $color }} 4%, transparent));
                border-color: color-mix(in srgb, {{ $color }} 20%, transparent)">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg shadow-sm" style="background: {{ $color }}">
            <x-icon name="archive" class="h-4 w-4 text-white" />
        </span>

        <div class="min-w-0 flex-1">
            {{-- breadcrumb: Library → module drawer → subfolders --}}
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                @if ($library)
                    <button type="button" wire:click="closeDrawer"
                            class="font-bold {{ $inLibraryWall ? 'text-eo-text' : 'text-eo-muted hover:text-eo-text' }}">
                        All documents
                    </button>
                @endif

                @if ($activeModule !== null || (! $library && $activeModule === null))
                    @if ($library)<span class="text-eo-muted">/</span>@endif
                    <button type="button" wire:click="goToRoot"
                            class="font-bold {{ $trail ? 'text-eo-muted hover:text-eo-text' : 'text-eo-text' }}">
                        {{ \App\Models\Event::moduleLabel($activeModule) }}
                    </button>
                @endif

                @foreach ($trail as $node)
                    <span class="text-eo-muted">/</span>
                    <button type="button" wire:click="openFolder({{ $node->id }})"
                            class="font-bold {{ $loop->last ? 'text-eo-text' : 'text-eo-muted hover:text-eo-text' }}">
                        {{ $node->name }}
                    </button>
                @endforeach
            </div>
            <p class="mt-0.5 text-eyebrow text-eo-muted">
                @if ($inLibraryWall)
                    Every drawer in this event — open one to file or read its papers.
                @else
                    {{ $documents->count() }} {{ \Illuminate\Support\Str::plural('document', $documents->count()) }}
                    @if ($folders->isNotEmpty()) · {{ $folders->count() }} {{ \Illuminate\Support\Str::plural('subfolder', $folders->count()) }} @endif
                @endif
            </p>
        </div>

        @if (! $inLibraryWall)
            <div class="flex shrink-0 items-center gap-1.5">
                @if ($trail)
                    <button type="button" wire:click="upOne"
                            class="rounded-lg bg-eo-bg px-2.5 py-1.5 text-eyebrow font-bold text-eo-text hover:bg-eo-line">↑ Up</button>
                @endif
                <button type="button" wire:click="$toggle('addingFolder')"
                        class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold text-white transition hover:opacity-90"
                        style="background: {{ $color }}">＋ Subfolder</button>
            </div>
        @endif
    </div>

    {{-- ══ new subfolder ══ --}}
    @if ($addingFolder && ! $inLibraryWall)
        <div class="flex items-center gap-2 border-b border-eo-line bg-eo-workspace/40 px-5 py-2.5">
            <input type="text" wire:model="newFolder" wire:keydown.enter.prevent="addFolder"
                   placeholder="Folder name — “Signed contracts”, “Quotes”, “Floor plans”…"
                   class="eo-input h-9 flex-1 text-sm" autofocus>
            <x-eo.button variant="navy" wire:click="addFolder" class="h-9 px-4 text-xs">Create</x-eo.button>
            <button type="button" wire:click="$set('addingFolder', false)"
                    class="h-9 rounded-xl px-3 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
        </div>
    @endif

    <div @class(["p-4" => $dock, "p-5" => ! $dock])>

        {{-- ══ the wall: one coloured drawer per module ══ --}}
        @if ($inLibraryWall)
            <div @class(["grid gap-2" => $dock, "grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" => ! $dock])>
                @foreach ($wall as $d)
                    <button type="button" wire:click="openDrawer(@js($d['key']))"
                            class="group/d flex items-center gap-3 rounded-2xl border border-eo-line bg-white p-3 text-left transition hover:-translate-y-0.5 hover:shadow-eo"
                            style="border-left: 3px solid {{ $d['color'] }}">
                        <span class="shrink-0">{!! $folderSvg($d['color']) !!}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-eo-text">{{ $d['label'] }}</span>
                            <span class="block text-eyebrow text-eo-muted">
                                {{ $d['documents'] }} {{ \Illuminate\Support\Str::plural('file', $d['documents']) }}
                                @if ($d['folders']) · {{ $d['folders'] }} {{ \Illuminate\Support\Str::plural('folder', $d['folders']) }} @endif
                            </span>
                            @unless ($d['enabled'])
                                <span class="mt-0.5 inline-block rounded-full bg-eo-bg px-1.5 text-eyebrow font-bold uppercase text-eo-muted">module off</span>
                            @endunless
                        </span>
                    </button>
                @endforeach
            </div>
        @else

            {{-- ══ subfolders ══ --}}
            @if ($folders->isNotEmpty())
                <div @class(["mb-4 grid gap-2" => $dock, "mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" => ! $dock])>
                    @foreach ($folders as $f)
                        <div wire:key="fold-{{ $f->id }}"
                             class="group/f flex items-center gap-3 rounded-2xl border border-eo-line bg-white p-3 transition hover:shadow-eo"
                             style="border-left: 3px solid {{ $color }}">
                            <button type="button" wire:click="openFolder({{ $f->id }})" class="shrink-0">
                                {!! $folderSvg($color, 'h-8 w-10') !!}
                            </button>
                            <div class="min-w-0 flex-1">
                                <input type="text" value="{{ $f->name }}"
                                       wire:change="renameFolder({{ $f->id }}, $event.target.value)"
                                       class="{{ $inp }} !text-xs font-bold">
                                <p class="px-2 text-eyebrow text-eo-muted">
                                    {{ $f->documents_count }} {{ \Illuminate\Support\Str::plural('file', $f->documents_count) }}
                                </p>
                            </div>
                            <x-confirm title="Delete “{{ $f->name }}”?"
                                       body="Its files move up to {{ \App\Models\Event::moduleLabel($activeModule) }}."
                                       confirm="Delete" run="$wire.deleteFolder({{ $f->id }})"
                                       class="shrink-0 rounded-lg px-1.5 py-1 text-eyebrow font-bold text-eo-muted opacity-100 transition hover:bg-eo-risk-soft hover:text-eo-risk sm:opacity-0 sm:group-hover/f:opacity-100">✕</x-confirm>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ══ drop zone ══ --}}
            <div x-data="{ over: false }"
                 @dragover.prevent="over = true"
                 @dragleave.prevent="over = false"
                 @drop.prevent="over = false; $refs.file.files = $event.dataTransfer.files; $refs.file.dispatchEvent(new Event('change'))"
                 @click="$refs.file.click()"
                 :class="over ? 'border-solid' : 'border-dashed'"
                 class="relative cursor-pointer rounded-[1.35rem] border-2 px-5 py-6 text-center shadow-eo transition"
                 :style="over ? 'border-color: {{ $color }}; background: {{ $color }}0f' : 'border-color: #e6e9ee; background: transparent'">
                <input type="file" x-ref="file" wire:model="upload" class="hidden">

                <div wire:loading wire:target="upload" class="text-xs font-semibold" style="color: {{ $color }}">Uploading…</div>

                <div wire:loading.remove wire:target="upload">
                    @if ($upload)
                        {{-- staged: name it and confirm where it belongs --}}
                        <div class="mx-auto max-w-xl text-left" @click.stop>
                            <p class="mb-2 flex items-center gap-2 text-xs font-bold text-eo-text">
                                <span class="rounded-md px-1.5 py-0.5 text-eyebrow font-black text-white" style="background: {{ $color }}">
                                    {{ strtoupper(pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'FILE') }}
                                </span>
                                <span class="truncate">{{ $upload->getClientOriginalName() }}</span>
                            </p>
                            <div @class(["grid gap-2" => $dock, "grid gap-2 sm:grid-cols-[minmax(0,1fr)_180px]" => ! $dock])>
                                <div>
                                    <label class="eo-label !mb-1">Document name</label>
                                    <input type="text" wire:model="name" class="eo-input h-9 text-sm" placeholder="Signed hotel contract">
                                    @error('name')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="eo-label !mb-1">Belongs to</label>
                                    <select wire:model="assignTo" class="eo-select h-9 text-sm">
                                        <option value="">Event-wide</option>
                                        @foreach (\App\Models\Event::HUB_MODULES as $key => [$label, $cat, $icon])
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end gap-2">
                                <button type="button" wire:click="cancel"
                                        class="h-9 rounded-xl px-3 text-xs font-semibold text-eo-muted hover:text-eo-text">Discard</button>
                                <x-eo.button variant="navy" wire:click="store" class="h-9 px-5 text-xs">Save document</x-eo.button>
                            </div>
                        </div>
                    @else
                        <p class="text-xs font-semibold text-eo-text">
                            Drop a file here<span class="text-eo-muted"> or click to browse</span>
                        </p>
                        <p class="mt-0.5 text-eyebrow text-eo-muted">
                            Filed under <span class="font-semibold" style="color: {{ $color }}">{{ \App\Models\Event::moduleLabel($activeModule) }}</span>
                            @if ($trail) → {{ collect($trail)->last()->name }} @endif · up to 25 MB
                        </p>
                        @error('upload')<p class="mt-1.5 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    @endif
                </div>
            </div>

            {{-- ══ documents ══ --}}
            @if ($documents->isNotEmpty())
                <div class="mt-4 divide-y divide-eo-line rounded-2xl border border-eo-line">
                    @foreach ($documents as $doc)
                        <div wire:key="doc-{{ $doc->id }}" class="group/doc flex items-center gap-3 px-4 py-2.5 hover:bg-eo-workspace/40">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-eyebrow font-black text-white"
                                  style="background: {{ $color }}">{{ $doc->extension() }}</span>

                            <div class="min-w-0 flex-1">
                                <input type="text" value="{{ $doc->name }}"
                                       wire:change="rename({{ $doc->id }}, $event.target.value)"
                                       class="{{ $inp }} !text-xs font-semibold">
                                <p class="truncate px-2 text-eyebrow text-eo-muted">
                                    {{ $doc->original_name }} · {{ $doc->sizeForHumans() }}
                                    @if ($doc->uploader) · {{ $doc->uploader->name }} @endif
                                    · {{ $doc->created_at->format('j M Y') }}
                                </p>
                            </div>

                            @if ($allFolders->isNotEmpty())
                                <select wire:change="moveToFolder({{ $doc->id }}, $event.target.value)"
                                        class="{{ $dock ? 'hidden' : 'hidden lg:block' }} shrink-0 rounded-lg border border-eo-line bg-white px-2 py-1 text-eyebrow text-eo-text focus:border-eo-teal focus:outline-none"
                                        title="Move to a subfolder">
                                    <option value="">— {{ \App\Models\Event::moduleLabel($activeModule) }} root —</option>
                                    @foreach ($allFolders as $f)
                                        <option value="{{ $f->id }}" @selected($doc->folder_id === $f->id)>{{ $f->name }}</option>
                                    @endforeach
                                </select>
                            @endif

                            <select wire:change="reassign({{ $doc->id }}, $event.target.value)"
                                    class="{{ $dock ? 'hidden' : 'hidden lg:block' }} shrink-0 rounded-lg border border-eo-line bg-white px-2 py-1 text-eyebrow text-eo-text focus:border-eo-teal focus:outline-none"
                                    title="File under a different module">
                                @foreach (\App\Models\Event::HUB_MODULES as $mKey => $mMeta)
                                    <option value="{{ $mKey }}" @selected($doc->module === $mKey)>{{ $mMeta[0] }}</option>
                                @endforeach
                            </select>

                            <div class="flex shrink-0 items-center gap-1">
                                @if ($doc->isViewable())
                                    <a href="{{ route('events.documents.view', [$event, $doc]) }}" target="_blank"
                                       class="rounded-lg bg-eo-bg px-2.5 py-1.5 text-eyebrow font-bold text-eo-text hover:bg-eo-line">View</a>
                                @endif
                                <a href="{{ route('events.documents.download', [$event, $doc]) }}"
                                   class="rounded-lg bg-eo-bg px-2.5 py-1.5 text-eyebrow font-bold text-eo-text hover:bg-eo-line">Download</a>
                                <x-confirm title="Delete “{{ $doc->name }}”?"
                                           body="The file is removed for good."
                                           confirm="Delete" run="$wire.delete({{ $doc->id }})"
                                           class="rounded-lg px-1.5 py-1.5 text-eyebrow font-bold text-eo-muted opacity-100 transition hover:bg-eo-risk-soft hover:text-eo-risk sm:opacity-0 sm:group-hover/doc:opacity-100">✕</x-confirm>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif (! $upload)
                <div class="mt-4">
                    <x-empty icon="archive" title="Nothing filed here yet"
                             hint="Drop a file above, or drag one in, to file it under {{ \App\Models\Event::moduleLabel($activeModule) }}." />
                </div>
            @endif
        @endif
    </div>
