<?php

namespace App\Livewire\Hub;

use App\Models\Event;
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
    }

    /** Autosave whenever a bound field changes (fields use wire:model.blur). */
    public function updated(string $name): void
    {
        if (str_starts_with($name, 'data') || $name === 'version') {
            $this->persist();
        }
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
     * Turn the approved brief into the working plan: phases + tasks, milestones,
     * budget categories, risk register, sponsorship packages and approval gates.
     * Idempotent — safe to re-run after editing the brief.
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
        $brief->version = $this->version;
        $brief->status = $this->status;
        $brief->template = $this->template;
        $brief->approved_at = $this->status === 'approved' ? now() : null;
        $brief->save();
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.hub.brief-tab', [
            'sections' => EventBrief::SECTIONS,
            'infoFields' => EventBrief::INFO_FIELDS,
            'twocolHeads' => EventBrief::TWOCOL_HEADS,
            'templates' => BriefTemplates::TEMPLATES,
        ]);
    }
}
