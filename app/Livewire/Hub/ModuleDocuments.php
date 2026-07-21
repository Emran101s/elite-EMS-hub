<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventDocumentFolder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The document drawer, in two guises:
 *
 *  - Scoped  (`module` set)  — the strip riding under every module tab. Shows
 *    that module's subfolders and files, with a drop zone.
 *  - Library (`library` true) — the Documents module: every module's drawer as
 *    a coloured folder wall you can open.
 *
 * Module folders are derived from HUB_MODULES rather than stored, so every
 * event has the same drawers on day one and none can be accidentally deleted.
 * Only subfolders are rows.
 */
class ModuleDocuments extends Component
{
    use WithFileUploads;

    public Event $event;

    /** HUB_MODULES key this drawer is scoped to; null on the event-wide drawer. */
    public ?string $module = null;

    /** True on the Documents tab, which browses every module. */
    public bool $library = false;

    /** True for the collapsible right-edge dock that rides on every module tab. */
    public bool $dock = false;

    /** Which module drawer the library is currently inside; null = the wall. */
    public ?string $openModule = null;

    /** Which subfolder we are inside; null = the drawer root. */
    public ?int $folderId = null;

    // ── Upload staging ──────────────────────────────────────────
    public $upload = null;

    public string $name = '';

    public ?string $assignTo = null;

    // ── New subfolder ───────────────────────────────────────────
    public string $newFolder = '';

    public bool $addingFolder = false;

    public function mount(Event $event, ?string $module = null, bool $library = false, bool $dock = false): void
    {
        $this->event = $event;
        $this->module = $module;
        $this->library = $library;
        $this->dock = $dock;
        $this->assignTo = $module;
    }

    /** The module whose drawer is on screen right now. */
    public function activeModule(): ?string
    {
        return $this->library ? $this->openModule : $this->module;
    }

    // ── Navigation ──────────────────────────────────────────────

    public function openDrawer(?string $module): void
    {
        $this->openModule = array_key_exists((string) $module, Event::HUB_MODULES) ? $module : null;
        $this->folderId = null;
        $this->assignTo = $this->openModule;
    }

    public function closeDrawer(): void
    {
        $this->openModule = null;
        $this->folderId = null;
    }

    public function openFolder(int $id): void
    {
        $folder = $this->event->documentFolders()->find($id);

        if ($folder) {
            $this->folderId = $folder->id;
        }
    }

    public function upOne(): void
    {
        $folder = $this->folderId ? $this->event->documentFolders()->find($this->folderId) : null;
        $this->folderId = $folder?->parent_id;
    }

    public function goToRoot(): void
    {
        $this->folderId = null;
    }

    // ── Folders ─────────────────────────────────────────────────

    public function addFolder(): void
    {
        Gate::authorize('write');
        $name = trim($this->newFolder);

        if ($name === '') {
            return;
        }

        $this->event->documentFolders()->create([
            'module' => $this->activeModule(),
            'parent_id' => $this->folderId,
            'name' => Str::limit($name, 120, ''),
            'position' => (int) $this->event->documentFolders()->max('position') + 1,
        ]);

        $this->reset(['newFolder', 'addingFolder']);
    }

    public function renameFolder(int $id, string $value): void
    {
        Gate::authorize('write');

        if (trim($value) !== '') {
            $this->event->documentFolders()->whereKey($id)->update(['name' => Str::limit(trim($value), 120, '')]);
        }
    }

    public function deleteFolder(int $id): void
    {
        Gate::authorize('write');

        // Subfolders cascade; the documents inside fall back to the drawer root
        // rather than vanishing with the folder.
        $folder = $this->event->documentFolders()->find($id);

        if (! $folder) {
            return;
        }

        $this->event->documents()->where('folder_id', $folder->id)->update(['folder_id' => null]);
        $folder->delete();

        if ($this->folderId === $id) {
            $this->folderId = null;
        }
    }

    // ── Documents ───────────────────────────────────────────────

    /** Dropping a file stages it and proposes a readable name. */
    public function updatedUpload(): void
    {
        Gate::authorize('write');
        $this->validate(['upload' => ['required', 'file', 'max:25600']]);   // 25 MB

        $this->name = Str::of(pathinfo($this->upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')->squish()->limit(120, '')->title()->toString();

        $this->assignTo = $this->activeModule();
    }

    public function store(): void
    {
        Gate::authorize('write');
        $this->validate([
            'upload' => ['required', 'file', 'max:25600'],
            'name' => ['required', 'string', 'max:160'],
        ]);

        $module = array_key_exists((string) $this->assignTo, Event::HUB_MODULES) ? $this->assignTo : null;

        // A staged file keeps its folder only when that folder is in the module
        // it is being filed under — otherwise it lands at that module's root.
        $folder = $this->folderId ? $this->event->documentFolders()->find($this->folderId) : null;
        $folderId = $folder && $folder->module === $module ? $folder->id : null;

        // Read every piece of metadata BEFORE storing: store() moves the file out
        // of livewire-tmp and removes the temporary copy, so any getSize() or
        // getMimeType() call afterwards fails on a file that no longer exists.
        $originalName = Str::limit($this->upload->getClientOriginalName(), 200, '');
        $mime = $this->upload->getMimeType();
        $size = $this->upload->getSize();

        $path = $this->upload->store("event-documents/{$this->event->id}", 'local');

        $this->event->documents()->create([
            'module' => $module,
            'folder_id' => $folderId,
            'name' => trim($this->name),
            'original_name' => $originalName,
            'path' => $path,
            'disk' => 'local',
            'mime' => $mime,
            'size' => $size,
            'uploaded_by' => auth()->id(),
        ]);

        $this->cancel();
        session()->flash('status', 'Document filed.');
    }

    public function cancel(): void
    {
        $this->reset(['upload', 'name']);
        $this->assignTo = $this->activeModule();
        $this->resetErrorBag();
    }

    public function rename(int $id, string $value): void
    {
        Gate::authorize('write');

        if (trim($value) !== '') {
            $this->event->documents()->whereKey($id)->update(['name' => Str::limit(trim($value), 160, '')]);
        }
    }

    /** Refiles a document under another module, back at that module's root. */
    public function reassign(int $id, string $value): void
    {
        Gate::authorize('write');

        $this->event->documents()->whereKey($id)->update([
            'module' => array_key_exists($value, Event::HUB_MODULES) ? $value : null,
            'folder_id' => null,
        ]);
    }

    public function moveToFolder(int $id, string $value): void
    {
        Gate::authorize('write');

        $folder = $value !== '' ? $this->event->documentFolders()->find((int) $value) : null;

        $this->event->documents()->whereKey($id)->update([
            'folder_id' => $folder?->id,
            'module' => $folder ? $folder->module : $this->activeModule(),
        ]);
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        // Deleting the row removes the stored file too (EventDocument::booted).
        $this->event->documents()->whereKey($id)->get()->each->delete();
    }

    /** Per-module counts for the library wall. */
    public function wall()
    {
        $byModule = $this->event->documents()->get()->groupBy(fn ($d) => $d->module ?? '');
        $folders = $this->event->documentFolders()->get()->groupBy(fn ($f) => $f->module ?? '');

        return collect([null, ...array_keys(Event::HUB_MODULES)])
            ->map(fn (?string $key) => [
                'key' => $key,
                'label' => Event::moduleLabel($key),
                'color' => Event::moduleColor($key),
                'icon' => Event::moduleIcon($key),
                'documents' => $byModule->get($key ?? '', collect())->count(),
                'folders' => $folders->get($key ?? '', collect())->count(),
                'enabled' => $key === null || $this->event->moduleEnabled($key),
            ])
            // Drawers you have filed something into always show, even if the
            // module was later switched off — otherwise the papers disappear.
            ->filter(fn (array $d) => $d['enabled'] || $d['documents'] > 0 || $d['folders'] > 0)
            ->values();
    }

    public function render()
    {
        $module = $this->activeModule();
        $inLibraryWall = $this->library && $this->openModule === null;

        $folders = $inLibraryWall ? collect() : $this->event->documentFolders()
            ->where('module', $module)
            ->where('parent_id', $this->folderId)
            ->withCount('documents')
            ->get();

        $documents = $inLibraryWall ? collect() : $this->event->documents()
            ->where('module', $module)
            ->where('folder_id', $this->folderId)
            ->with('uploader')
            ->latest()
            ->get();

        $current = $this->folderId ? $this->event->documentFolders()->find($this->folderId) : null;

        return view('livewire.hub.module-documents', [
            'wall' => $inLibraryWall ? $this->wall() : collect(),
            'inLibraryWall' => $inLibraryWall,
            'activeModule' => $module,
            'color' => Event::moduleColor($module),
            'folders' => $folders,
            'documents' => $documents,
            'dock' => $this->dock,
            'trail' => $current?->trail() ?? [],
            // Somewhere to move a file to, without leaving the drawer.
            'allFolders' => $this->event->documentFolders()->where('module', $module)->get(),
        ]);
    }
}
