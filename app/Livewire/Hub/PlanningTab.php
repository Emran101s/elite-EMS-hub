<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventPlanItem;
use App\Models\User;
use App\Services\PlanRoadmap;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PlanningTab extends Component
{
    public Event $event;

    // ── task form ──
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $formCategoryId = null;

    public ?int $formParentId = null;

    public string $title = '';

    public string $status = 'todo';

    public string $priority = 'medium';

    public ?int $assignee_id = null;

    /** Owners of the task being edited — a task can have several. */
    public array $owner_ids = [];

    public string $starts_on = '';

    public string $due_on = '';

    public string $notes = '';

    // ── phase (category) management ──
    public string $newCategoryName = '';

    public ?int $editingCategoryId = null;

    public string $categoryEditName = '';

    /** Task ids whose subtasks are revealed. */
    public array $expanded = [];

    public function toggleExpand(int $id): void
    {
        if (($k = array_search($id, $this->expanded, true)) !== false) {
            unset($this->expanded[$k]);
            $this->expanded = array_values($this->expanded);
        } else {
            $this->expanded[] = $id;
        }
    }

    public function mount(): void
    {
        $this->event->ensurePlanCategories();
    }

    // ── Tasks ─────────────────────────────────────────────────
    public function newItem(?int $categoryId = null, ?int $parentId = null): void
    {
        $this->justSaved = false;
        $this->reset(['editingId', 'title', 'assignee_id', 'owner_ids', 'starts_on', 'due_on', 'notes']);
        $this->status = 'todo';
        $this->priority = 'medium';
        $this->formParentId = $parentId;
        $this->formCategoryId = $parentId
            ? $this->event->planItems()->whereKey($parentId)->value('category_id')
            : ($categoryId ?: (int) $this->event->planCategories()->value('id'));
        $this->resetValidation();
        $this->showForm = true;
    }

    public function newSubItem(int $parentId): void
    {
        if (! in_array($parentId, $this->expanded, true)) {
            $this->expanded[] = $parentId;
        }
        $this->newItem(null, $parentId);
    }

    public function editItem(int $id): void
    {
        $item = $this->event->planItems()->findOrFail($id);
        $this->justSaved = false;
        $this->editingId = $item->id;
        $this->formCategoryId = $item->category_id;
        $this->formParentId = $item->parent_id;
        $this->title = $item->title;
        $this->status = in_array($item->status, EventPlanItem::STATUSES, true) ? $item->status : 'todo';
        $this->priority = array_key_exists($item->priority, EventPlanItem::PRIORITIES) ? $item->priority : 'medium';
        $this->assignee_id = $item->assignee_id;
        $this->owner_ids = $item->owners()->pluck('users.id')->all();
        $this->starts_on = $item->starts_on?->format('Y-m-d') ?? '';
        $this->due_on = $item->due_on?->format('Y-m-d') ?? '';
        $this->notes = $item->notes ?? '';
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Add or remove an owner. A task can be owned by several people. */
    public function toggleOwner(int $userId): void
    {
        if (($k = array_search($userId, $this->owner_ids, true)) !== false) {
            unset($this->owner_ids[$k]);
            $this->owner_ids = array_values($this->owner_ids);
        } else {
            $this->owner_ids[] = $userId;
        }

        $this->justSaved = false;

        if ($this->editingId) {
            $this->syncOwners($this->event->planItems()->findOrFail($this->editingId));
        }
    }

    /** Persist the owner list; `assignee_id` mirrors the first owner for anything still reading it. */
    private function syncOwners(EventPlanItem $item): void
    {
        $ids = User::whereIn('id', $this->owner_ids)->pluck('id')->all();
        $item->owners()->sync($ids);
        $item->update(['assignee_id' => $ids[0] ?? null]);
    }

    /** Fields the inspector autosaves as you edit an existing item. */
    private const FORM_FIELDS = ['title', 'status', 'priority', 'starts_on', 'due_on', 'notes', 'formCategoryId'];

    /** Set right after an explicit Save, so the panel can confirm it landed. */
    public bool $justSaved = false;

    /** Live-save the inspector for an existing item (new items are created with saveItem()). */
    public function updated(string $name): void
    {
        if (! $this->editingId || ! in_array($name, self::FORM_FIELDS, true)) {
            return;
        }

        $this->justSaved = false;

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'status' => ['required', Rule::in(EventPlanItem::STATUSES)],
            'priority' => ['required', Rule::in(array_keys(EventPlanItem::PRIORITIES))],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $this->event->planItems()->whereKey($this->editingId)->update([
            'category_id' => $this->formCategoryId,
            'title' => $this->title,
            'status' => $this->status,
            'priority' => $this->priority,
            'starts_on' => $this->starts_on ?: null,
            'due_on' => $this->due_on ?: null,
            'notes' => $this->notes ?: null,
        ]);
    }

    public function closePanel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId']);
    }

    public function saveItem(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'status' => ['required', Rule::in(EventPlanItem::STATUSES)],
            'priority' => ['required', Rule::in(array_keys(EventPlanItem::PRIORITIES))],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $data = [
            'category_id' => $this->formCategoryId,
            'parent_id' => $this->formParentId,
            'title' => $this->title,
            'status' => $this->status,
            'priority' => $this->priority,
            'starts_on' => $this->starts_on ?: null,
            'due_on' => $this->due_on ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            $item = $this->event->planItems()->findOrFail($this->editingId);
            $item->update($data);
            $this->syncOwners($item);
            $this->justSaved = true;

            return;
        }

        $data['sort_order'] = (int) $this->event->planItems()
            ->where('category_id', $this->formCategoryId)
            ->where('parent_id', $this->formParentId)
            ->max('sort_order') + 1;

        // Keep the inspector open on the item we just created so it can be fleshed out.
        $item = $this->event->planItems()->create($data);
        $this->syncOwners($item);
        $this->editingId = $item->id;
        $this->justSaved = true;
    }

    public function deleteItem(int $id): void
    {
        $this->event->planItems()->whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->closePanel();
        }
    }

    public function setStatus(int $id, string $status): void
    {
        if (in_array($status, EventPlanItem::STATUSES, true)) {
            $this->event->planItems()->whereKey($id)->update(['status' => $status]);
        }
    }

    /** Inline owner assignment from the task chart. */
    public function assign(int $id, $userId): void
    {
        $userId = $userId ? (int) $userId : null;
        if ($userId !== null && ! User::whereKey($userId)->exists()) {
            return;
        }
        $this->event->planItems()->whereKey($id)->update(['assignee_id' => $userId]);
    }

    /** Inline date edit from the task chart ($which = 'start'|'due'). */
    public function setDate(int $id, string $which, ?string $value): void
    {
        $col = $which === 'due' ? 'due_on' : 'starts_on';
        $this->event->planItems()->whereKey($id)->update([$col => $value ?: null]);
    }

    public function toggleDone(int $id): void
    {
        $item = $this->event->planItems()->findOrFail($id);
        $item->update(['status' => $item->status === 'done' ? 'todo' : 'done']);
    }

    /** Drag-to-reschedule: shift start + due by whole days. */
    public function moveTask(int $id, int $deltaDays): void
    {
        if ($deltaDays === 0) {
            return;
        }
        $t = $this->event->planItems()->findOrFail($id);
        if ($t->starts_on) {
            $t->starts_on = $t->starts_on->copy()->addDays($deltaDays);
        }
        if ($t->due_on) {
            $t->due_on = $t->due_on->copy()->addDays($deltaDays);
        }
        $t->save();
    }

    /** Drag-to-resize: move just the start ('left') or due ('right') edge, without crossing over. */
    public function resizeTask(int $id, string $edge, int $deltaDays): void
    {
        if ($deltaDays === 0) {
            return;
        }
        $t = $this->event->planItems()->findOrFail($id);
        if (! $t->starts_on || ! $t->due_on) {
            return; // only bars (two dates) resize
        }
        if ($edge === 'left') {
            $new = $t->starts_on->copy()->addDays($deltaDays);
            $t->starts_on = $new->lte($t->due_on) ? $new : $t->due_on->copy();
        } else {
            $new = $t->due_on->copy()->addDays($deltaDays);
            $t->due_on = $new->gte($t->starts_on) ? $new : $t->starts_on->copy();
        }
        $t->save();
    }

    // ── Phase (category) management ───────────────────────────
    public function addCategory(): void
    {
        $this->validate(['newCategoryName' => ['required', 'string', 'max:60']]);
        $name = trim($this->newCategoryName);
        if ($this->event->planCategories()->get()->contains(fn ($c) => mb_strtolower($c->name) === mb_strtolower($name))) {
            $this->addError('newCategoryName', 'That phase already exists.');

            return;
        }
        $this->event->planCategories()->create([
            'name' => $name,
            'position' => (int) $this->event->planCategories()->max('position') + 1,
        ]);
        $this->newCategoryName = '';
    }

    public function startRenameCategory(int $id): void
    {
        $cat = $this->event->planCategories()->findOrFail($id);
        $this->editingCategoryId = $cat->id;
        $this->categoryEditName = $cat->name;
    }

    public function cancelRenameCategory(): void
    {
        $this->reset(['editingCategoryId', 'categoryEditName']);
    }

    public function saveCategoryName(): void
    {
        $this->validate(['categoryEditName' => ['required', 'string', 'max:60']]);
        $this->event->planCategories()->findOrFail($this->editingCategoryId)
            ->update(['name' => trim($this->categoryEditName)]);
        $this->reset(['editingCategoryId', 'categoryEditName']);
    }

    public function deleteCategory(int $id): void
    {
        $cat = $this->event->planCategories()->findOrFail($id);
        $target = $this->event->planCategories()->where('id', '!=', $cat->id)->orderBy('position')->first();
        if ($target) {
            $this->event->planItems()->where('category_id', $cat->id)->update(['category_id' => $target->id]);
        }
        $cat->delete();
    }

    public function reorderCategories(array $ids): void
    {
        foreach (array_values($ids) as $pos => $id) {
            $this->event->planCategories()->whereKey((int) $id)->update(['position' => $pos]);
        }
    }

    public function render(PlanRoadmap $roadmapService)
    {
        $items = $this->event->planItems()->with(['assignee', 'owners'])->get();
        $phases = $this->event->planCategories()->get();

        $axis = $roadmapService->axis($this->event, $items);
        $stats = $roadmapService->stats($this->event, $items);
        $selected = $this->editingId ? $items->firstWhere('id', $this->editingId) : null;

        return view('livewire.hub.planning-tab', [
            'roadmap' => $axis,
            'planTree' => $roadmapService->tree($items, $phases, $axis),
            'categories' => $phases,
            'users' => User::orderBy('name')->get(),
            'selected' => $selected,
            'selectedKids' => $selected ? $items->where('parent_id', $selected->id)->sortBy('sort_order')->values() : collect(),
            'selectedParent' => $selected?->parent_id ? $items->firstWhere('id', $selected->parent_id) : null,
            'stats' => $stats,
            'daysToEvent' => $stats['daysToEvent'],
            'eventDay' => $stats['eventDay'],
        ]);
    }
}
