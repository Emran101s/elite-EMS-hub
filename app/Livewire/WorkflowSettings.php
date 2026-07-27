<?php

namespace App\Livewire;

use App\Models\TaxonomyTerm;
use App\Support\Workflow;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Workflow states: your wording, your colours, the platform's keys.
 *
 * Deliberately narrower than Types & Lists. There is no Add and no Delete,
 * because the code reasons about these keys — `won` creates an event, `done`
 * takes a task out of the count. What you can change is what they are called
 * and what colour they wear, which was never a process decision.
 */
class WorkflowSettings extends Component
{
    /** In the URL, so a particular set can be linked to. */
    #[Url(as: 'set')]
    public string $set = 'event_stage';

    /** key => wording, edited live. */
    public array $labels = [];

    /** key => hex, edited live. */
    public array $colors = [];

    public function mount(): void
    {
        if (! array_key_exists($this->set, Workflow::SETS)) {
            $this->set = array_key_first(Workflow::SETS);
        }

        if (TaxonomyTerm::where('taxonomy', 'like', 'state:%')->count() === 0) {
            Workflow::seed();
        }

        $this->load();
    }

    public function pick(string $set): void
    {
        if (array_key_exists($set, Workflow::SETS)) {
            $this->set = $set;
            $this->load();
        }
    }

    private function load(): void
    {
        Workflow::forget();

        $rows = Workflow::rows($this->set);
        $this->labels = $rows->pluck('label', 'key')->all();
        $this->colors = $rows->pluck('color', 'key')->all();
    }

    public function save(): void
    {
        $this->validate([
            'labels.*' => ['required', 'string', 'max:40'],
            'colors.*' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'labels.*.required' => 'Every state needs a name.',
            'colors.*.regex' => 'A colour must be a hex value like #22C55E.',
        ]);

        // Only the states this set actually declares — the keys are the
        // platform's, so nothing arriving from the browser can add one.
        foreach (array_keys(Workflow::meta($this->set, 'states')) as $key) {
            TaxonomyTerm::updateOrCreate(
                ['taxonomy' => Workflow::taxonomy($this->set), 'key' => $key],
                [
                    'label' => trim($this->labels[$key] ?? ''),
                    'color' => $this->colors[$key] ?? null,
                    'is_system' => true,
                ],
            );
        }

        Workflow::forget();
        $this->load();

        session()->flash('status', Workflow::meta($this->set, 'label').' updated.');
    }

    /** Put a set back to the wording and colours it shipped with. */
    public function restore(): void
    {
        TaxonomyTerm::where('taxonomy', Workflow::taxonomy($this->set))->delete();

        Workflow::forget();
        Workflow::seed();
        $this->load();

        session()->flash('status', Workflow::meta($this->set, 'label').' put back to the defaults.');
    }

    public function reorder(array $keys): void
    {
        foreach ($keys as $position => $key) {
            TaxonomyTerm::where('taxonomy', Workflow::taxonomy($this->set))
                ->where('key', $key)->update(['position' => $position]);
        }

        Workflow::forget();
        $this->load();
    }

    public function render()
    {
        $rows = Workflow::rows($this->set);

        return view('livewire.workflow-settings', [
            'sets' => collect(Workflow::SETS)->map(fn ($m, $k) => [
                'key' => $k,
                'label' => $m['label'],
                'count' => count($m['states']),
            ])->values(),
            'rows' => $rows,
            'setLabel' => Workflow::meta($this->set, 'label'),
            'setNote' => Workflow::meta($this->set, 'note'),
            'changed' => $rows->contains(fn ($r) => $r['label'] !== $r['default_label']
                || strcasecmp((string) $r['color'], $r['default_color']) !== 0),
        ])->layout('components.layouts.app', [
            'title' => 'Statuses & colours',
            'subtitle' => 'What the platform calls each step, and the colour it wears.',
            'crumbs' => [
                ['label' => 'Command Center', 'href' => route('home')],
                ['label' => 'Settings', 'href' => route('settings.index')],
                ['label' => 'Statuses & colours'],
            ],
        ]);
    }
}
