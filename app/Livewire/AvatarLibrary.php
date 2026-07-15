<?php

namespace App\Livewire;

use App\Models\EventAvatar;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app', ['title' => 'Event Avatars'])]
class AvatarLibrary extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('nullable|string|max:120')]
    public string $subtitle = '';

    #[Validate('required|string|max:40')]
    public string $category = 'conference';

    #[Validate('nullable|string|max:200')]
    public string $best_for = '';

    public bool $is_active = true;

    /** Uploaded image (required on create, optional on edit). */
    #[Validate('nullable|image|max:4096')]
    public $image = null;

    public string $search = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'subtitle', 'best_for', 'image']);
        $this->category = 'conference';
        $this->is_active = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $a = EventAvatar::findOrFail($id);
        $this->editingId = $a->id;
        $this->name = $a->name;
        $this->subtitle = $a->subtitle ?? '';
        $this->category = $a->category ?? 'conference';
        $this->best_for = $a->best_for ?? '';
        $this->is_active = (bool) $a->is_active;
        $this->image = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        if (! $this->editingId) {
            $this->validate(['image' => 'required|image|max:4096']);
        }
        $this->validate();

        $data = [
            'name' => trim($this->name),
            'subtitle' => trim($this->subtitle) ?: null,
            'category' => $this->category,
            'best_for' => trim($this->best_for) ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->image) {
            // Public disk (symlinked at public/storage) → rendered via asset($image_path).
            $path = $this->image->store('avatars', 'public');
            $data['image_path'] = 'storage/'.$path;
            $data['thumbnail_path'] = null;
        }

        if ($this->editingId) {
            EventAvatar::whereKey($this->editingId)->update($data);
        } else {
            $data['slug'] = $this->uniqueSlug($data['name']);
            $data['colors'] = ['#0B1F3A', '#D4AF37', '#FFFFFF'];
            $data['recommended_types'] = [];
            $data['sort_order'] = (EventAvatar::max('sort_order') ?? 0) + 1;
            EventAvatar::create($data);
        }

        $this->showForm = false;
        $this->reset(['editingId', 'image']);
        session()->flash('status', 'Avatar saved.');
    }

    public function toggleActive(int $id): void
    {
        $a = EventAvatar::findOrFail($id);
        $a->update(['is_active' => ! $a->is_active]);
    }

    public function delete(int $id): void
    {
        // events.avatar_id is nullOnDelete → events using this avatar fall back to the placeholder.
        EventAvatar::whereKey($id)->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'avatar';
        $slug = $base;
        $i = 2;
        while (EventAvatar::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function render()
    {
        $avatars = EventAvatar::when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->withCount('events')
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('livewire.avatar-library', [
            'avatars' => $avatars,
            'categories' => EventAvatar::CATEGORIES,
        ]);
    }
}
