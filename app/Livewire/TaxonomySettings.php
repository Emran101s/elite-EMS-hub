<?php

namespace App\Livewire;

use App\Models\TaxonomyTerm;
use App\Support\Taxonomy;
use Livewire\Attributes\Url;
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
    /** In the URL, so a particular list can be linked to and bookmarked. */
    #[Url(as: 'list')]
    public string $taxonomy = 'event_type';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $label = '';

    public string $key = '';

    public ?string $color = null;

    public string $note = '';

    /** Which term this one sits under. One level only — see the migration. */
    public ?int $parent_id = null;

    public function mount(): void
    {
        // A list that arrives in a later release would otherwise open empty
        // here while its dropdown quietly fell back to the constant. One
        // grouped count catches that; seeding is per-term firstOrCreate.
        if (! array_key_exists($this->taxonomy, Taxonomy::LISTS)) {
            $this->taxonomy = array_key_first(Taxonomy::LISTS);
        }

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

    public function newTerm(?int $parentId = null): void
    {
        $this->reset(['editingId', 'label', 'key', 'color', 'note', 'parent_id']);

        // Adding from under a parent pre-fills it, which is the only way most
        // people will ever create a sub-category.
        $this->parent_id = $parentId;
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
        $this->parent_id = $term->parent_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:9'],
            'note' => ['nullable', 'string', 'max:160'],
        ]);

        $parentId = $this->resolvedParentId();

        if ($this->editingId) {
            // The key is not in this update on purpose: records store it.
            // Moving a term under a different parent is fine — it regroups it,
            // it does not change what any record holds.
            TaxonomyTerm::findOrFail($this->editingId)->update($data + ['parent_id' => $parentId]);
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
                'parent_id' => $parentId,
                'key' => $key,
                'position' => (int) TaxonomyTerm::in($this->taxonomy)
                    ->where('parent_id', $parentId)->max('position') + 1,
            ]);
        }

        Taxonomy::forget();
        $this->showForm = false;
    }

    /**
     * The parent this term may actually have.
     *
     * Nesting is one level deep on purpose, so three things are refused and
     * silently flattened rather than erroring: a term parented to itself, a
     * term parented to something that is already a child, and a term that has
     * children of its own being made into one.
     */
    private function resolvedParentId(): ?int
    {
        if (! $this->parent_id) {
            return null;
        }

        $parent = TaxonomyTerm::in($this->taxonomy)->whereKey($this->parent_id)->first();

        if (! $parent || $parent->id === $this->editingId || $parent->parent_id) {
            return null;
        }

        if ($this->editingId && TaxonomyTerm::where('parent_id', $this->editingId)->exists()) {
            return null;
        }

        return $parent->id;
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
        $all = TaxonomyTerm::in($this->taxonomy)->get();
        $usage = Taxonomy::usage($this->taxonomy);

        // Eighteen lists is too many to scan as one column, so the picker is
        // grouped — and the counts come from one query rather than eighteen.
        $counts = TaxonomyTerm::query()->active()
            ->selectRaw('taxonomy, count(*) as n')->groupBy('taxonomy')->pluck('n', 'taxonomy');

        return view('livewire.taxonomy-settings', [
            'groups' => Taxonomy::groups()->map(fn ($lists) => $lists->map(fn ($meta) => [
                'key' => $meta['key'],
                'label' => $meta['label'],
                'count' => (int) ($counts[$meta['key']] ?? 0),
            ])->values()),
            // Roots with their children, in one query pair — the screen is
            // the tree, so it is fetched as one.
            'terms' => TaxonomyTerm::in($this->taxonomy)->roots()->with('children')->get(),
            // What each term is carrying, so hiding or deleting one is a
            // decision rather than a guess.
            'usage' => $usage,
            // Counted across the whole list, children included.
            'totals' => [
                'terms' => $all->count(),
                'inUse' => $all->filter(fn ($t) => ($usage[$t->key] ?? 0) > 0)->count(),
                'records' => $all->sum(fn ($t) => $usage[$t->key] ?? 0),
            ],
            // Only roots can be a parent: nesting is one level deep.
            'parents' => $all->whereNull('parent_id')
                ->when($this->editingId, fn ($c) => $c->where('id', '!=', $this->editingId)),
            'listLabel' => Taxonomy::meta($this->taxonomy, 'label'),
            'listDescription' => Taxonomy::meta($this->taxonomy, 'note'),
            'usesColor' => (bool) Taxonomy::meta($this->taxonomy, 'color'),
            'storesLabel' => Taxonomy::meta($this->taxonomy, 'stores') === 'label',
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
