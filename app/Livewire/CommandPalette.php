<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Livewire\Component;

/**
 * The header search, made real: one box that finds events, tasks, suppliers,
 * venues, speakers and people, opened with ⌘K / Ctrl-K from anywhere.
 */
class CommandPalette extends Component
{
    public string $q = '';

    /** @return array<string, array<int, array{label:string, sub:?string, href:string}>> */
    private function results(): array
    {
        $q = trim($this->q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $like = '%'.$q.'%';

        $groups = [
            'Events' => Event::whereNull('archived_at')->where('name', 'like', $like)
                ->orderBy('starts_at')->limit(5)->get()
                ->map(fn ($e) => [
                    'label' => $e->name,
                    'sub' => trim(($e->city ?? '').' · '.($e->starts_at?->format('M Y') ?? '')),
                    'href' => route('events.hub', $e),
                ]),

            'Tasks' => Task::with('event')->where('title', 'like', $like)
                ->orderByRaw("status = 'done'")->limit(5)->get()
                ->map(fn ($t) => [
                    'label' => $t->title,
                    'sub' => ($t->event?->name ?? 'No event').' · '.$t->stageLabel(),
                    'href' => $t->event ? route('events.hub', [$t->event, 'tab' => 'tasks']) : route('tasks.index'),
                ]),

            'Speakers' => EventSpeaker::with('event')->where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($s) => [
                    'label' => $s->name,
                    'sub' => $s->event?->name,
                    'href' => $s->event ? route('events.hub', [$s->event, 'tab' => 'speakers']) : route('home'),
                ]),

            'Suppliers' => Supplier::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($s) => [
                    'label' => $s->name,
                    'sub' => str($s->category ?? '')->replace('_', ' ')->title()->toString() ?: null,
                    'href' => route('suppliers.index'),
                ]),

            'Venues' => Venue::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($v) => ['label' => $v->name, 'sub' => $v->city, 'href' => route('venues.index')]),

            'Team' => User::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($u) => ['label' => $u->name, 'sub' => $u->title ?? $u->roleLabel(), 'href' => route('team.index')]),
        ];

        return collect($groups)->filter(fn ($g) => $g->isNotEmpty())->map->all()->all();
    }

    public function render()
    {
        return view('livewire.command-palette', ['groups' => $this->results()]);
    }
}
