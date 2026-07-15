<?php

namespace App\Livewire;

use App\Models\Event;
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
        // Its booths go back to the shared tray.
        $this->event->exhibitors()->where('hall_id', $id)->update(['hall_id' => null, 'booth_x' => null, 'booth_y' => null]);
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

    // ── Booths (metres, hall-scoped) ──────────────────────────
    public function placeBooth(int $id): void
    {
        $hall = $this->currentHall();
        $ex = $this->event->exhibitors()->whereKey($id)->first();
        if (! $hall || ! $ex) {
            return;
        }
        [$w, $h] = $ex->booth_w_m && $ex->booth_h_m ? [$ex->booth_w_m, $ex->booth_h_m] : $this->boothMeters($ex->booth_size);
        $ex->update([
            'hall_id' => $hall->id,
            'booth_x' => round($hall->width_m / 2, 2),
            'booth_y' => round($hall->length_m / 2, 2),
            'booth_w_m' => $w,
            'booth_h_m' => $h,
        ]);
        $this->selectItem('booth', $id);
    }

    public function moveBooth(int $id, float $x, float $y): void
    {
        $hall = $this->currentHall();
        if (! $hall) {
            return;
        }
        $this->event->exhibitors()->whereKey($id)->where('hall_id', $hall->id)->update([
            'booth_x' => round(max(0, min($hall->width_m, $x)), 2),
            'booth_y' => round(max(0, min($hall->length_m, $y)), 2),
        ]);
    }

    public function resizeBooth(int $id, string $axis, float $delta): void
    {
        $hall = $this->currentHall();
        $ex = $this->event->exhibitors()->whereKey($id)->first();
        if (! $hall || ! $ex) {
            return;
        }
        $w = $ex->booth_w_m ?: 3;
        $h = $ex->booth_h_m ?: 3;
        if ($axis === 'w' || $axis === 'both') {
            $w = round(max(0.5, min($hall->width_m, $w + $delta)), 2);
        }
        if ($axis === 'h' || $axis === 'both') {
            $h = round(max(0.5, min($hall->length_m, $h + $delta)), 2);
        }
        $ex->update(['booth_w_m' => $w, 'booth_h_m' => $h]);
    }

    public function unplaceBooth(int $id): void
    {
        $this->event->exhibitors()->whereKey($id)->update(['hall_id' => null, 'booth_x' => null, 'booth_y' => null]);
        if ($this->selectedKind === 'booth' && $this->selectedId === $id) {
            $this->deselect();
        }
    }

    /** Grid-lay every unplaced booth into the current hall, in metres. */
    public function autoArrange(): void
    {
        $hall = $this->currentHall();
        if (! $hall) {
            return;
        }
        $unplaced = $this->event->exhibitors()->whereNull('hall_id')->where('status', '!=', 'cancelled')->get();
        $x = 1.0;
        $y = 1.0;
        $rowH = 0.0;
        foreach ($unplaced as $ex) {
            [$w, $h] = $ex->booth_w_m && $ex->booth_h_m ? [$ex->booth_w_m, $ex->booth_h_m] : $this->boothMeters($ex->booth_size);
            if ($x + $w > $hall->width_m) {
                $x = 1.0;
                $y += $rowH + 1.0;
                $rowH = 0.0;
            }
            $ex->update([
                'hall_id' => $hall->id,
                'booth_x' => round($x + $w / 2, 2),
                'booth_y' => round($y + $h / 2, 2),
                'booth_w_m' => $w,
                'booth_h_m' => $h,
            ]);
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

        $exhibitors = $this->event->exhibitors()->where('status', '!=', 'cancelled')->orderBy('booth_number')->orderBy('company')->get();
        $placed = $exhibitors->filter(fn ($e) => $e->hall_id === $hall?->id && $e->booth_x !== null)->values();
        $unplaced = $exhibitors->filter(fn ($e) => $e->hall_id === null)->values();
        $fixtures = $hall?->fixtures ?? [];

        $selected = null;
        if ($this->selectedKind === 'booth') {
            $selected = $placed->firstWhere('id', $this->selectedId);
        } elseif ($this->selectedKind === 'fixture') {
            $selected = collect($fixtures)->firstWhere('id', $this->selectedId);
        }

        return view('livewire.exhibition-floor-plan', [
            'halls' => $halls,
            'hall' => $hall,
            'placed' => $placed,
            'unplaced' => $unplaced,
            'fixtures' => $fixtures,
            'fixturePresets' => self::FIXTURE_PRESETS,
            'selected' => $selected,
            'totalBooths' => $exhibitors->count(),
        ]);
    }
}
