<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use App\Models\EventBrief;
use App\Services\BriefGenerator;
use App\Support\BriefTemplates;
use Livewire\Component;

class BriefTab extends Component
{
    public Event $event;

    public int $briefId;

    /** @var array<string,mixed> */
    public array $data = [];

    public string $version = '1.0';

    public string $status = 'draft';

    public string $template = 'conference';

    public bool $saved = false;

    /** Counts from the last plan generation, e.g. ['tasks' => 14, 'risks' => 5, …]. */
    public array $generated = [];

    public function mount(Event $event): void
    {
        $this->event = $event;
        $brief = EventBrief::forEvent($event);
        $this->briefId = $brief->id;
        $this->data = $brief->data;
        $this->version = $brief->version;
        $this->status = $brief->status;
        $this->template = $brief->template;
        $this->hidden = $brief->hidden_sections ?? [];
    }

    /**
     * Sections taken off THIS brief.
     *
     * The twelve are the same on every event because a brief is a standard
     * document. Not every event has sponsors or an exhibition floor, though,
     * and a heading with nothing under it reads to a client as something
     * nobody bothered to fill in.
     *
     * @var list<string>
     */
    public array $hidden = [];

    /** Take a section off this brief. What was written in it is kept. */
    public function removeSection(string $key): void
    {
        Gate::authorize('write');

        if (! isset(EventBrief::SECTIONS[$key]) || in_array($key, $this->hidden, true)) {
            return;
        }

        $this->hidden[] = $key;
        $this->persist();
    }

    /** Put it back, with whatever was in it. */
    public function restoreSection(string $key): void
    {
        Gate::authorize('write');

        $this->hidden = array_values(array_diff($this->hidden, [$key]));
        $this->persist();
    }

    /** Section key that was just saved via its Save button (drives the "Saved ✓" flash). */
    public ?string $savedSection = null;

    /** Autosave whenever a bound field changes (fields use wire:model.blur). */
    public function updated(string $name): void
    {
        if (str_starts_with($name, 'data') || $name === 'version') {
            $this->persist();
            // Any edit clears a stale "Saved" flash on whichever section it belonged to.
            $this->savedSection = null;
        }
    }

    /** Explicit per-section save — persists and flashes a confirmation on that section. */
    public function saveSection(string $key): void
    {
        $this->persist();
        $this->savedSection = $key;
    }

    public function addRow(string $section): void
    {
        $type = EventBrief::SECTIONS[$section][2] ?? null;

        $this->data[$section][] = match ($type) {
            'twocol' => ['area' => '', 'notes' => ''],
            'kpi' => ['kpi' => '', 'target' => ''],
            'approval' => ['name' => '', 'title' => ''],
            default => '', // bullets
        };
        $this->persist();
    }

    public function removeRow(string $section, int $i): void
    {
        unset($this->data[$section][$i]);
        $this->data[$section] = array_values($this->data[$section]);
        $this->persist();
    }

    public function moveRow(string $section, int $i, int $dir): void
    {
        $j = $i + $dir;
        if (! isset($this->data[$section][$i], $this->data[$section][$j])) {
            return;
        }
        [$this->data[$section][$i], $this->data[$section][$j]] = [$this->data[$section][$j], $this->data[$section][$i]];
        $this->persist();
    }

    public function toggleApproved(): void
    {
        $this->status = $this->status === 'approved' ? 'draft' : 'approved';
        $this->persist();
    }

    public function resetToTemplate(): void
    {
        $this->data = EventBrief::defaultData($this->event, $this->template);
        $this->persist();
    }

    /**
     * Turn the approved brief into working records: budget categories, risk
     * register and sponsorship packages. Idempotent — safe to re-run.
     */
    public function generatePlan(BriefGenerator $generator): void
    {
        if ($this->status !== 'approved') {
            $this->addError('generate', 'Approve the brief before generating the plan.');

            return;
        }

        $brief = EventBrief::findOrFail($this->briefId);
        $this->generated = $generator->generate($brief);
        $this->dispatch('brief-generated');
    }

    /** Switch to another of the 5 templates — same design, different content set. */
    public function switchTemplate(string $key): void
    {
        $this->template = BriefTemplates::key($key);
        $this->data = EventBrief::defaultData($this->event, $this->template);
        $this->persist();
    }

    private function persist(): void
    {
        $brief = EventBrief::find($this->briefId);
        if (! $brief) {
            return;
        }
        $brief->data = $this->data;
        $brief->hidden_sections = array_values($this->hidden);
        $brief->version = $this->version;
        $brief->status = $this->status;
        $brief->template = $this->template;
        $brief->approved_at = $this->status === 'approved' ? now() : null;
        $brief->save();
        $this->saved = true;
    }

    public function render()
    {
        // The paper and the editor read the same list, so what is on screen is
        // exactly what exports — including what has been taken off.
        $live = collect(EventBrief::SECTIONS)->reject(fn ($m, $k) => in_array($k, $this->hidden, true))->all();

        return view('livewire.hub.brief-tab', [
            'sections' => $live,
            'hiddenSections' => collect(EventBrief::SECTIONS)
                ->filter(fn ($m, $k) => in_array($k, $this->hidden, true))->all(),
            'infoFields' => EventBrief::INFO_FIELDS,
            'twocolHeads' => EventBrief::TWOCOL_HEADS,
            'templates' => BriefTemplates::TEMPLATES,
        ]);
    }
}
