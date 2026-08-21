<?php

namespace App\Livewire\VenueStudio;

use App\Models\Venue;
use App\Models\VenueDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A venue's own document library — contracts, floor plans, technical specs,
 * insurance, permits. Deliberately flat: no folder hierarchy like
 * Hub\ModuleDocuments — a venue's document volume doesn't need one.
 */
class DocumentsTab extends Component
{
    use WithFileUploads;

    public Venue $venue;

    public $upload = null;

    public string $name = '';

    public string $category = 'other';

    public ?string $status = null;

    public function updatedUpload(): void
    {
        Gate::authorize('write');
        $this->validate(['upload' => ['required', 'file', 'max:25600']]);

        $this->name = Str::of(pathinfo($this->upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->replace(['_', '-'], ' ')->squish()->limit(120, '')->title()->toString();
    }

    public function store(): void
    {
        Gate::authorize('write');
        $this->validate([
            'upload' => ['required', 'file', 'max:25600'],
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(VenueDocument::CATEGORIES)],
        ]);

        $status = $this->category === 'contract'
            ? (in_array($this->status, VenueDocument::CONTRACT_STATUSES, true) ? $this->status : 'draft')
            : null;

        // Read every piece of metadata before storing — store() moves the file
        // out of livewire-tmp, which invalidates these calls afterward.
        $originalName = Str::limit($this->upload->getClientOriginalName(), 200, '');
        $mime = $this->upload->getMimeType();
        $size = $this->upload->getSize();
        $path = $this->upload->store("venue-documents/{$this->venue->id}", 'local');

        $this->venue->documents()->create([
            'category' => $this->category,
            'status' => $status,
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
        $this->reset(['upload', 'name', 'status']);
        $this->category = 'other';
        $this->resetErrorBag();
    }

    public function rename(int $id, string $value): void
    {
        Gate::authorize('write');
        $value = trim($value);
        if ($value === '') {
            return;
        }
        $this->venue->documents()->whereKey($id)->update(['name' => Str::limit($value, 160, '')]);
    }

    public function updateStatus(int $id, string $value): void
    {
        Gate::authorize('write');
        $doc = $this->venue->documents()->whereKey($id)->firstOrFail();

        if ($doc->category === 'contract' && in_array($value, VenueDocument::CONTRACT_STATUSES, true)) {
            $doc->update(['status' => $value]);
        }
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        $this->venue->documents()->whereKey($id)->get()->each->delete();
    }

    public function render()
    {
        return view('livewire.venue-studio.documents-tab', [
            'byCategory' => $this->venue->documents()->latest()->get()->groupBy('category'),
            'categories' => VenueDocument::CATEGORIES,
            'statuses' => VenueDocument::CONTRACT_STATUSES,
        ]);
    }
}
