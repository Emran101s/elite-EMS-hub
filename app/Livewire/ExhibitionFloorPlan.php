<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventBooth;
use App\Models\EventExhibitionHall;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Exhibition Floor Plan'])]
class ExhibitionFloorPlan extends Component
{
    public Event $event;

    /** Current hall being edited. */
    public ?int $hallId = null;

    /** Current hall dimensions (metres), bound to the inputs. */
    public string $hallW = '';

    public string $hallL = '';

    public string $newHallName = '';

    /** Selection for the properties panel: kind = 'booth' | 'fixture'. */
    public ?string $selectedKind = null;

    public $selectedId = null;

    /** type => [label, width_m, length_m, color]. */
    public const FIXTURE_PRESETS = [
        'entrance' => ['Entrance', 5, 1.5, '#0EA5E9'],
        'stage' => ['Stage', 8, 4, '#1E3352'],
        'registration' => ['Registration', 5, 3, '#6366F1'],
        'catering' => ['Catering', 5, 4, '#14B8A6'],
        'lounge' => ['Lounge', 5, 4, '#B45309'],
        'info' => ['Info Desk', 3, 2, '#64748B'],
        'restrooms' => ['Restrooms', 3, 2, '#94A3B8'],
        'aisle' => ['Aisle', 12, 1.5, '#CBD5E1'],
    ];

    public function mount(Event $event): void
    {
        $this->event = $event;
        $hall = $event->ensureExhibitionHall();
        $this->hallId = $hall->id;
        $this->syncHallDims($hall);
    }

    private function currentHall(): ?EventExhibitionHall
    {
        return $this->event->exhibitionHalls()->find($this->hallId)
            ?? $this->event->exhibitionHalls()->first();
    }

    private function syncHallDims(?EventExhibitionHall $hall): void
    {
        $hall ??= $this->currentHall();
        $this->hallW = $hall ? rtrim(rtrim(number_format($hall->width_m, 2), '0'), '.') : '';
        $this->hallL = $hall ? rtrim(rtrim(number_format($hall->length_m, 2), '0'), '.') : '';
    }

    /** Booth size (metres) parsed from the exhibitor's "3×3" booth_size; default 3×3. */
    private function boothMeters(?string $size): array
    {
        if ($size && preg_match('/(\d+(?:\.\d+)?)\s*[x×X]\s*(\d+(?:\.\d+)?)/u', $size, $m)) {
            return [max(0.5, min(100, (float) $m[1])), max(0.5, min(100, (float) $m[2]))];
        }

        return [3.0, 3.0];
    }

    // ── Hall management ───────────────────────────────────────
    public function selectHall(int $id): void
    {
        $this->hallId = $id;
        $this->deselect();
        $this->syncHallDims(null);
    }

    public function addHall(): void
    {
        $n = $this->event->exhibitionHalls()->count();
        $hall = $this->event->exhibitionHalls()->create([
            'name' => trim($this->newHallName) ?: 'Hall '.chr(65 + $n),
            'width_m' => 20,
            'length_m' => 15,
            'position' => (int) $this->event->exhibitionHalls()->max('position') + 1,
        ]);
        $this->newHallName = '';
        $this->selectHall($hall->id);
    }

    public function renameHall(int $id, string $name): void
    {
        if ($name = trim($name)) {
            $this->event->exhibitionHalls()->whereKey($id)->update(['name' => Str::limit($name, 60, '')]);
        }
    }

    public function deleteHall(int $id): void
    {
        if ($this->event->exhibitionHalls()->count() <= 1) {
            session()->flash('status', 'An event needs at least one hall.');

            return;
        }
        // Deleting a hall destroys its booth inventory; the buyers survive and
        // return to the waiting tray with their booth number cleared.
        foreach ($this->event->booths()->where('hall_id', $id)->with('exhibitor')->get() as $booth) {
            $booth->exhibitor?->update(['booth_number' => null]);
        }
        $this->event->exhibitionHalls()->whereKey($id)->delete();
        $this->selectHall((int) $this->event->exhibitionHalls()->value('id'));
    }

    public function updatedHallW(): void
    {
        $this->persistDims();
    }

    public function updatedHallL(): void
    {
        $this->persistDims();
    }

    private function persistDims(): void
    {
        $hall = $this->currentHall();
        if (! $hall) {
            return;
        }
        $hall->update([
            'width_m' => is_numeric($this->hallW) && (float) $this->hallW > 0 ? min(500, (float) $this->hallW) : $hall->width_m,
            'length_m' => is_numeric($this->hallL) && (float) $this->hallL > 0 ? min(500, (float) $this->hallL) : $hall->length_m,
        ]);
    }

    // ── Booths: sellable inventory (metres, hall-scoped) ─────
    /** Next free booth number for this event: B01, B02, … */
    private function nextBoothNumber(): string
    {
        $taken = $this->event->booths()->pluck('number')->flip();
        for ($i = 1; $i < 1000; $i++) {
            $n = 'B'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if (! isset($taken[$n])) {
                return $n;
            }
        }

        return 'B'.uniqid();
    }

    /** Draw a new booth on the floor — inventory first, buyer later. */
    public function addBooth(): void
    {
        $hall = $this->currentHall();
        if (! $hall) {
            return;
        }

        // New booths default to the last price set on this event, so pricing a
        // whole row doesn't mean retyping the number every time.
        $price = $this->event->booths()->latest('id')->value('price_cents') ?? 0;

        $booth = $this->event->booths()->create([
            'hall_id' => $hall->id,
            'number' => $this->nextBoothNumber(),
            'price_cents' => $price,
            'x' => round($hall->width_m / 2, 2),
            'y' => round($hall->length_m / 2, 2),
            'w_m' => 3,
            'h_m' => 3,
        ]);
        $this->selectItem('booth', $booth->id);
    }

    /** Tray shortcut: create a booth already sold-to/reserved-for this exhibitor. */
    public function placeBooth(int $exhibitorId): void
    {
        $hall = $this->currentHall();
        $ex = $this->event->exhibitors()->whereKey($exhibitorId)->whereDoesntHave('booth')->first();
        if (! $hall || ! $ex) {
            return;
        }
        [$w, $h] = $this->boothMeters($ex->booth_size);

        $booth = $this->event->booths()->create([
            'hall_id' => $hall->id,
            'exhibitor_id' => $ex->id,
            'number' => trim((string) $ex->booth_number) ?: $this->nextBoothNumber(),
            'price_cents' => $ex->fee_cents ?: 0,
            'x' => round($hall->width_m / 2, 2),
            'y' => round($hall->length_m / 2, 2),
            'w_m' => $w,
            'h_m' => $h,
        ]);
        $ex->update(['booth_number' => $booth->number]);
        $this->selectItem('booth', $booth->id);
    }

    public function moveBooth(int $id, float $x, float $y): void
    {
        $hall = $this->currentHall();
        if (! $hall) {
            return;
        }
        $this->event->booths()->whereKey($id)->where('hall_id', $hall->id)->update([
            'x' => round(max(0, min($hall->width_m, $x)), 2),
            'y' => round(max(0, min($hall->length_m, $y)), 2),
        ]);
    }

    public function resizeBooth(int $id, string $axis, float $delta): void
    {
        $hall = $this->currentHall();
        $booth = $this->event->booths()->whereKey($id)->first();
        if (! $hall || ! $booth) {
            return;
        }
        $w = $booth->w_m;
        $h = $booth->h_m;
        if ($axis === 'w' || $axis === 'both') {
            $w = round(max(0.5, min($hall->width_m, $w + $delta)), 2);
        }
        if ($axis === 'h' || $axis === 'both') {
            $h = round(max(0.5, min($hall->length_m, $h + $delta)), 2);
        }
        $booth->update(['w_m' => $w, 'h_m' => $h]);
    }

    public function setBoothNumber(int $id, string $number): void
    {
        $number = trim($number);
        if ($number === '' || $this->event->booths()->where('number', $number)->whereKeyNot($id)->exists()) {
            return;   // numbers stay unique per event
        }
        $booth = $this->event->booths()->whereKey($id)->first();
        $booth?->update(['number' => $number]);
        $booth?->exhibitor?->update(['booth_number' => $number]);
    }

    public function setBoothPrice(int $id, $price): void
    {
        if (! is_numeric($price) || (float) $price < 0) {
            return;
        }
        $this->event->booths()->whereKey($id)->update(['price_cents' => (int) round((float) $price * 100)]);
    }

    /** The sale: link a buyer to the booth. */
    public function assignExhibitor(int $boothId, $exhibitorId): void
    {
        $booth = $this->event->booths()->whereKey($boothId)->first();
        $ex = $this->event->exhibitors()->whereKey((int) $exhibitorId)->whereDoesntHave('booth')->first();
        if (! $booth || ! $ex || $booth->exhibitor_id) {
            return;
        }

        $booth->update(['exhibitor_id' => $ex->id]);
        // The exhibitor record follows the booth: number always, fee only when unset.
        $ex->update(['booth_number' => $booth->number]
            + ($ex->fee_cents ? [] : ['fee_cents' => $booth->price_cents]));
    }

    /** Undo the sale — the booth returns to available inventory. */
    public function releaseExhibitor(int $boothId): void
    {
        $booth = $this->event->booths()->whereKey($boothId)->first();
        if (! $booth || ! $booth->exhibitor_id) {
            return;
        }
        $booth->exhibitor?->update(['booth_number' => null]);
        $booth->update(['exhibitor_id' => null]);
    }

    public function deleteBooth(int $id): void
    {
        $booth = $this->event->booths()->whereKey($id)->first();
        if (! $booth) {
            return;
        }
        $booth->exhibitor?->update(['booth_number' => null]);
        $booth->delete();
        if ($this->selectedKind === 'booth' && $this->selectedId === $id) {
            $this->deselect();
        }
    }

    /** Grid-lay a booth for every exhibitor that doesn't have one yet. */
    public function autoArrange(): void
    {
        $hall = $this->currentHall();
        if (! $hall) {
            return;
        }
        $waiting = $this->event->exhibitors()->whereDoesntHave('booth')->where('status', '!=', 'cancelled')->get();
        $x = 1.0;
        $y = 1.0;
        $rowH = 0.0;
        foreach ($waiting as $ex) {
            [$w, $h] = $this->boothMeters($ex->booth_size);
            if ($x + $w > $hall->width_m) {
                $x = 1.0;
                $y += $rowH + 1.0;
                $rowH = 0.0;
            }
            $booth = $this->event->booths()->create([
                'hall_id' => $hall->id,
                'exhibitor_id' => $ex->id,
                'number' => trim((string) $ex->booth_number) ?: $this->nextBoothNumber(),
                'price_cents' => $ex->fee_cents ?: 0,
                'x' => round($x + $w / 2, 2),
                'y' => round($y + $h / 2, 2),
                'w_m' => $w,
                'h_m' => $h,
            ]);
            $ex->update(['booth_number' => $booth->number]);
            $x += $w + 1.0;
            $rowH = max($rowH, $h);
        }
    }

    // ── Fixtures (metres, per hall json) ──────────────────────
    private function updateFixtures(callable $fn): void
    {
        $hall = $this->currentHall();
        if (! $hall) {
            return;
        }
        $fx = collect($hall->fixtures ?? []);
        $hall->update(['fixtures' => collect($fn($fx))->values()->all()]);
    }

    public function addFixture(string $type): void
    {
        if (! array_key_exists($type, self::FIXTURE_PRESETS)) {
            return;
        }
        [$label, $w, $h] = self::FIXTURE_PRESETS[$type];
        $hall = $this->currentHall();
        $id = Str::random(8);
        $this->updateFixtures(fn ($fx) => $fx->push([
            'id' => $id, 'type' => $type, 'label' => $label,
            'x' => round($hall->width_m / 2, 2), 'y' => round($hall->length_m / 2, 2), 'w' => $w, 'h' => $h,
        ]));
        $this->selectItem('fixture', $id);
    }

    public function moveFixture(string $id, float $x, float $y): void
    {
        $hall = $this->currentHall();
        $this->updateFixtures(fn ($fx) => $fx->map(function ($f) use ($id, $x, $y, $hall) {
            if ($f['id'] === $id) {
                $f['x'] = round(max(0, min($hall->width_m, $x)), 2);
                $f['y'] = round(max(0, min($hall->length_m, $y)), 2);
            }

            return $f;
        }));
    }

    public function resizeFixture(string $id, string $axis, float $delta): void
    {
        $this->updateFixtures(fn ($fx) => $fx->map(function ($f) use ($id, $axis, $delta) {
            if ($f['id'] === $id) {
                if ($axis === 'w' || $axis === 'both') {
                    $f['w'] = round(max(0.5, min(200, ($f['w'] ?? 3) + $delta)), 2);
                }
                if ($axis === 'h' || $axis === 'both') {
                    $f['h'] = round(max(0.5, min(200, ($f['h'] ?? 2) + $delta)), 2);
                }
            }

            return $f;
        }));
    }

    public function renameFixture(string $id, string $label): void
    {
        $this->updateFixtures(fn ($fx) => $fx->map(function ($f) use ($id, $label) {
            if ($f['id'] === $id) {
                $f['label'] = Str::limit(trim($label), 40, '');
            }

            return $f;
        }));
    }

    public function removeFixture(string $id): void
    {
        $this->updateFixtures(fn ($fx) => $fx->reject(fn ($f) => $f['id'] === $id));
        if ($this->selectedKind === 'fixture' && $this->selectedId === $id) {
            $this->deselect();
        }
    }

    // ── Selection ─────────────────────────────────────────────
    public function deselect(): void
    {
        $this->selectedKind = $this->selectedId = null;
    }

    public function selectItem(string $kind, $id): void
    {
        if ($this->selectedKind === $kind && $this->selectedId === $id) {
            $this->deselect();
        } else {
            $this->selectedKind = $kind;
            $this->selectedId = $id;
        }
    }

    public function render()
    {
        $halls = $this->event->exhibitionHalls()->get();
        $hall = $halls->firstWhere('id', $this->hallId) ?? $halls->first();
        $this->hallId = $hall?->id;

        $allBooths = $this->event->booths()->with('exhibitor')->orderBy('number')->get();
        $booths = $allBooths->where('hall_id', $hall?->id)->values();

        // Exhibitors still waiting for a booth — the sales pipeline tray.
        $waiting = $this->event->exhibitors()->whereDoesntHave('booth')
            ->where('status', '!=', 'cancelled')->orderBy('company')->get();

        $fixtures = $hall?->fixtures ?? [];

        $selected = null;
        if ($this->selectedKind === 'booth') {
            $selected = $booths->firstWhere('id', $this->selectedId);
        } elseif ($this->selectedKind === 'fixture') {
            $selected = collect($fixtures)->firstWhere('id', $this->selectedId);
        }

        // The floor plan is a revenue dashboard: sold / reserved / available,
        // with money to match — across every hall of the event.
        $byStatus = $allBooths->groupBy(fn ($b) => $b->status());
        $sales = [
            'total' => $allBooths->count(),
            'sold' => $byStatus->get('sold', collect())->count(),
            'reserved' => $byStatus->get('reserved', collect())->count(),
            'available' => $byStatus->get('available', collect())->count(),
            'soldValue' => $byStatus->get('sold', collect())->sum('price_cents'),
            'pipelineValue' => $byStatus->get('reserved', collect())->sum('price_cents'),
            'openValue' => $byStatus->get('available', collect())->sum('price_cents'),
        ];

        return view('livewire.exhibition-floor-plan', [
            'halls' => $halls,
            'hall' => $hall,
            'booths' => $booths,
            'waiting' => $waiting,
            'fixtures' => $fixtures,
            'fixturePresets' => self::FIXTURE_PRESETS,
            'selected' => $selected,
            'sales' => $sales,
        ])->layoutData([
            'title' => 'Exhibition Floor Plan',
            'crumbs' => [
                ['label' => 'Command Center', 'href' => route('home')],
                ['label' => 'Events', 'href' => route('events.index')],
                ['label' => $this->event->name, 'href' => route('events.hub', $this->event)],
                ['label' => 'Exhibition', 'href' => route('events.hub', [$this->event, 'tab' => 'exhibition'])],
                ['label' => 'Floor Plan'],
            ],
        ]);
    }
}
