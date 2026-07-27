<?php

namespace App\Livewire;

use App\Models\TaxonomyTerm;
use App\Support\Taxonomy;
use Livewire\Component;

/**
 * The lists the platform draws from, made editable.
 *
 * The rule that keeps this safe: a term's KEY is what records store, so it is
 * set once and never changed. Everything people actually want to change — the
 * label, the colour, the order, whether it is still offered — is free.
 */
class TaxonomySettings extends Component
{
    public string $taxonomy = 'event_type';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $label = '';

    public string $key = '';

    public ?string $color = null;

    public string $note = '';

    public function mount(): void
    {
        // A list that arrives in a later release would otherwise open empty
        // here while its dropdown quietly fell back to the constant. One
        // grouped count catches that; seeding is per-term firstOrCreate.
        $filled = TaxonomyTerm::query()->distinct()->pluck('taxonomy')->all();

        if (array_diff(array_keys(Taxonomy::LISTS), $filled)) {
            Taxonomy::seed();
        }
    }

    public function pick(string $taxonomy): void
    {
        if (array_key_exists($taxonomy, Taxonomy::LISTS)) {
            $this->taxonomy = $taxonomy;
            $this->showForm = false;
        }
    }

    public function newTerm(): void
    {
        $this->reset(['editingId', 'label', 'key', 'color', 'note']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $term = TaxonomyTerm::findOrFail($id);

        $this->editingId = $term->id;
        $this->label = $term->label;
        $this->key = $term->key;
        $this->color = $term->color;
        $this->note = (string) $term->note;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:9'],
            'note' => ['nullable', 'string', 'max:160'],
        ]);

        if ($this->editingId) {
            // The key is not in this update on purpose: records store it.
            TaxonomyTerm::findOrFail($this->editingId)->update($data);
        } else {
            // The key is derived from the label once, here, and then frozen.
            // Uniqueness is checked by hand because the value being validated
            // is derived rather than typed, so it is not a component property.
            $key = Taxonomy::deriveKey($this->taxonomy, $this->label);

            if ($key === '') {
                $this->addError('label', 'Give this a name with at least one letter or number.');

                return;
            }

            if (TaxonomyTerm::in($this->taxonomy)->where('key', $key)->exists()) {
                $this->addError('key', '“'.$this->label.'” is already in this list.');

                return;
            }

            TaxonomyTerm::create($data + [
                'taxonomy' => $this->taxonomy,
                'key' => $key,
                'position' => (int) TaxonomyTerm::in($this->taxonomy)->max('position') + 1,
            ]);
        }

        Taxonomy::forget();
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $term = TaxonomyTerm::findOrFail($id);
        $term->update(['is_active' => ! $term->is_active]);
        Taxonomy::forget();
    }

    public function delete(int $id): void
    {
        $term = TaxonomyTerm::findOrFail($id);

        // Two reasons a term cannot go: the platform's own code names it, or
        // records are on it. Either way it is turned off instead, so nothing
        // that already refers to it loses its label.
        if ($term->is_system || (Taxonomy::usage($this->taxonomy)[$term->key] ?? 0) > 0) {
            $term->update(['is_active' => false]);
        } else {
            $term->delete();
        }

        Taxonomy::forget();
    }

    public function reorder(array $ids): void
    {
        foreach ($ids as $position => $id) {
            TaxonomyTerm::whereKey($id)->where('taxonomy', $this->taxonomy)->update(['position' => $position]);
        }

        Taxonomy::forget();
    }

    public function render()
    {
        [$label, $description, $usesColor] = Taxonomy::LISTS[$this->taxonomy];
        $storesLabel = Taxonomy::LISTS[$this->taxonomy][3] === 'label';

        return view('livewire.taxonomy-settings', [
            'lists' => collect(Taxonomy::LISTS)->map(fn ($meta, $key) => [
                'key' => $key,
                'label' => $meta[0],
                'count' => TaxonomyTerm::in($key)->active()->count(),
            ])->values(),
            'terms' => TaxonomyTerm::in($this->taxonomy)->get(),
            // What each term is carrying, so hiding or deleting one is a
            // decision rather than a guess.
            'usage' => Taxonomy::usage($this->taxonomy),
            'listLabel' => $label,
            'listDescription' => $description,
            'usesColor' => $usesColor,
            'storesLabel' => $storesLabel,
        ])->layout('components.layouts.app', [
            'title' => 'Types & lists',
            'subtitle' => 'The lists every event, deal and budget draws from — yours to change.',
            'crumbs' => [
                ['label' => 'Command Center', 'href' => route('home')],
                ['label' => 'Settings', 'href' => route('settings.index')],
                ['label' => 'Types & lists'],
            ],
        ]);
    }
}
