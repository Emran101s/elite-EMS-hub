<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventRoom;
use App\Models\Requirement;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Venue'])]
class RoomLayoutBuilder extends Component
{
    public Event $event;

    public EventRoom $room;

    /** Placed elements: [id, type, x, y, rot, seats, w, h]. */
    public array $elements = [];

    /** Room real-world dimensions (metres). Kept as strings for the inputs. */
    public string $width_m = '';

    public string $length_m = '';

    /** Equipment needs: item => quantity. */
    public array $equipment = [];

    /** ── Seating generator ── */
    public bool $showSeatModal = false;

    public string $seatArr = 'theater';

    public string $seatTarget = '';

    public string $seatSize = '0.6';

    public string $seatComfort = 'standard';

    public string $seatGapCm = '8';   // space between chairs (cm)

    public string $seatAisles = '1';

    public bool $seatLabels = false;

    public bool $seatFill = false;

    public string $tableSeats = '10';

    // ── Table Designer: professional per-table control (centimetres) ──
    public string $uHeadTables = '2';       // tables across the head

    public string $uArmTables = '3';        // tables per side arm

    public string $uPerTable = '3';         // chairs per table (outer edge)

    public string $tableW_cm = '180';       // rectangular table long side

    public string $tableH_cm = '60';        // rectangular table depth

    public string $roundDia_cm = '180';     // round/banquet table diameter

    public string $seatMsg = '';

    /** Currently selected element id (for the properties panel). */
    public ?string $selectedId = null;

    /** Bulk selection for delete-many. */
    public array $selectedIds = [];

    /** Active workspace: 'floor' | 'equipment' | 'requirements' — kept in the URL so refresh/deep-links land on the same tab. */
    #[Url(except: 'floor')]
    public string $view = 'floor';

    /** Live search filter for the equipment catalogue. */
    public string $equipSearch = '';

    /** New custom equipment item name. */
    public string $customItem = '';

    /** Requirements editor (costed; total syncs to the budget). */
    public string $reqName = '';

    public string $reqCost = '';

    /** How many, and for how many days — blank means one of each. */
    public string $reqQty = '';

    public string $reqDays = '';

    public function mount(Event $event, EventRoom $room): void
    {
        abort_unless($room->event_id === $event->id, 404);
        $this->event = $event;
        $this->room = $room;
        $this->elements = $room->layout ?? [];
        $this->equipment = $this->normaliseEquipment($room->equipment ?? []);
        $this->width_m = $room->width_m ? rtrim(rtrim(number_format((float) $room->width_m, 2, '.', ''), '0'), '.') : '';
        $this->length_m = $room->length_m ? rtrim(rtrim(number_format((float) $room->length_m, 2, '.', ''), '0'), '.') : '';
    }

    /** Coerce any stored shape (legacy int or rich array) into {qty,status,notes}. */
    private function normaliseEquipment(array $raw): array
    {
        $out = [];
        foreach ($raw as $item => $line) {
            $qty = is_array($line) ? (int) ($line['qty'] ?? 0) : (int) $line;
            if ($qty <= 0) {
                continue;
            }
            $status = is_array($line) && in_array($line['status'] ?? '', EventRoom::EQUIPMENT_STATUSES, true) ? $line['status'] : 'needed';
            $out[$item] = ['qty' => $qty, 'status' => $status, 'notes' => is_array($line) ? (string) ($line['notes'] ?? '') : ''];
        }

        return $out;
    }

    public function addElement(string $type): void
    {
        if (! array_key_exists($type, EventRoom::LAYOUT_PRESETS)) {
            return;
        }
        [, $seats, $w, $h] = EventRoom::LAYOUT_PRESETS[$type];

        // Basics drop at their real-world size when the room is to scale:
        // chair 60cm, rectangular table 180×60cm, round table 180cm.
        $dropCm = ['round' => [180, 180], 'table' => [180, 60], 'chair' => [60, 60], 'booth' => [200, 200]];
        if (($scale = $this->metreScale()) && isset($dropCm[$type])) {
            [$wcm, $hcm] = $dropCm[$type];
            $w = (int) round($wcm / 100 * $scale);
            $h = (int) round($hcm / 100 * $scale);
        }

        $id = Str::random(8);
        $n = count($this->elements);   // fan drops out so they don't stack on one spot
        $this->elements[] = [
            'id' => $id,
            'type' => $type,
            'x' => 480 + ($n % 5) * 26 - 52,
            'y' => 280 + (intdiv($n, 5) % 4) * 26,
            'rot' => 0,
            'seats' => $seats,
            'w' => $w, 'h' => $h,
        ];
        $this->selectedId = $id;
        $this->persist();
    }

    public function selectElement(string $id): void
    {
        $this->selectedId = $this->selectedId === $id ? null : $id;
    }

    /** Auto-generate a block of individual chairs in the chosen arrangement, sized to fit the room. */
    public function openSeatModal(): void
    {
        $this->seatMsg = '';
        $this->resetValidation();
        $this->showSeatModal = true;
    }

    public function generateSeating(): void
    {
        $this->validate([
            'seatArr' => ['required', Rule::in(array_keys(EventRoom::SEATING_ARRANGEMENTS))],
            'seatTarget' => [$this->seatFill ? 'nullable' : 'required', 'integer', 'min:1', 'max:5000'],
            'seatSize' => ['required', 'numeric', 'min:0.3', 'max:2'],
            'seatComfort' => ['required', Rule::in(array_keys(EventRoom::SEATING_COMFORT))],
            'seatGapCm' => ['required', 'numeric', 'min:0', 'max:60'],
        ]);

        $w = is_numeric($this->width_m) ? (float) $this->width_m : 0;
        $l = is_numeric($this->length_m) ? (float) $this->length_m : 0;
        if ($w <= 0 || $l <= 0) {
            $this->seatMsg = '';
            $this->addError('seatTarget', 'Set the room width & length (metres) first — see the Room panel.');

            return;
        }

        $seat = (float) $this->seatSize;
        $margin = 0.8;
        $front = 2.5; // reserve the front of the room for a stage / aisle
        $usableW = max($seat, $w - 2 * $margin);
        $usableL = max($seat, $l - $front - $margin);
        $roomArea = $w * $l;

        [, $seatGap, $tRowGap, $cRowGap] = EventRoom::SEATING_COMFORT[$this->seatComfort];
        $seatGap = max(0.0, (float) $this->seatGapCm / 100);   // explicit chair spacing wins
        $arr = $this->seatArr;
        $family = EventRoom::SEATING_ARRANGEMENTS[$arr][2];
        $target = $this->seatFill ? 100000 : (int) $this->seatTarget;

        $el = ['id' => Str::random(8), 'type' => 'seatblock', 'arr' => $arr, 'rot' => 0, 'seat_m' => $seat, 'x' => 480, 'y' => 310, 'labels' => $this->seatLabels];
        $roomMax = null;

        if ($family === 'rounds') {
            $dia = max(0.8, (float) $this->roundDia_cm / 100);
            $per = $arr === 'cabaret' ? 6 : max(4, min(12, (int) ($this->tableSeats ?: 10)));
            $gap = 1.4;
            $pitch = $dia + $gap;
            $tColsMax = max(1, (int) floor(($usableW + $gap) / $pitch));
            $tRowsMax = max(1, (int) floor(($usableL + $gap) / $pitch));
            $need = (int) ceil($target / $per);
            $tCols = min($tColsMax, max(1, $need));
            $tRows = min($tRowsMax, (int) ceil($need / $tCols));
            $el += ['tableDia_m' => $dia, 'perTable' => $per, 'tableGap_m' => $gap, 'tRows' => $tRows, 'tCols' => $tCols];
            $capacity = $tRows * $tCols * $per;
            $roomMax = $tRowsMax * $tColsMax * $per;
        } elseif ($family === 'perimeter') {
            $pitch = $seat + 0.10;
            $ushape = $arr === 'ushape';
            $div = $ushape ? 3.5 : 5; // 3 or 4 sides, cols ≈ 1.5·rows
            $rows = max(1, (int) round($target / $div));
            $cols = max(1, (int) round(1.5 * $rows));
            $maxW = max($pitch, $usableW - 2 * ($seat + 0.16));
            $maxL = max($pitch, $usableL - 2 * ($seat + 0.16));
            $cols = min($cols, 1 + (int) floor($maxW / $pitch));
            $rows = min($rows, 1 + (int) floor($maxL / $pitch));
            $el += ['pCols' => $cols, 'pRows' => $rows, 'colGap_m' => 0.10];
            $capacity = $ushape ? $cols + 2 * $rows : 2 * $cols + 2 * $rows;
        } elseif ($family === 'chevron') {
            $colGap = $seatGap;
            $rowGap = $tRowGap;
            $aisleGap = 1.4;
            $colPitch = $seat + $colGap;
            $rowPitch = $seat + $rowGap;
            $colsMax = max(2, (int) floor(($usableW - $aisleGap + $colGap) / $colPitch * 0.9));
            $rowsMax = max(1, (int) floor(($usableL + $rowGap) / $rowPitch));
            $cols = min($colsMax, max(2, $target));
            if ($cols % 2 !== 0) {
                $cols++;
            }
            $rows = min($rowsMax, (int) ceil($target / max(1, $cols)));
            $el += ['rows' => $rows, 'cols' => $cols, 'colGap_m' => $colGap, 'rowGap_m' => $rowGap, 'aisleGap_m' => $aisleGap, 'angle' => 12];
            $capacity = $rows * $cols;
            $roomMax = $rowsMax * $colsMax;
        } elseif ($family === 'ring') {
            $seatPitch = $seat + 0.15;
            $ringGap = 0.8;
            $maxR = max(1.2, min($usableW, $usableL) / 2);
            $placed = 0;
            $radius = 1.2;
            $guard = 0;
            while ($placed < $target && $radius <= $maxR && $guard++ < 60) {
                $n = max(3, (int) floor(2 * M_PI * $radius / $seatPitch));
                $placed += min($target - $placed, $n);
                $radius += $ringGap;
            }
            $capacity = max(1, $placed);
            $el += ['count' => $capacity, 'colGap_m' => 0.15, 'rowGap_m' => $ringGap];
            $roomMax = $this->seatFill ? $capacity : null;
        } else { // grid: theater / classroom
            $classroom = $arr === 'classroom';
            $colGap = $seatGap;
            $rowGap = $classroom ? $cRowGap : $tRowGap;
            $deskDepth = $classroom ? 0.45 : 0;
            $aisles = max(0, min(2, (int) $this->seatAisles));
            $aisleGap = 1.2;
            $colPitch = $seat + $colGap;
            $rowPitch = $seat + $deskDepth + $rowGap;
            $colsMax = max(1, (int) floor(($usableW - $aisles * $aisleGap + $colGap) / $colPitch));
            $rowsMax = max(1, (int) floor(($usableL + $rowGap) / $rowPitch));
            $cols = min($colsMax, $target);
            $rows = min($rowsMax, (int) ceil($target / max(1, $cols)));
            $el += ['rows' => $rows, 'cols' => $cols, 'colGap_m' => $colGap, 'rowGap_m' => $rowGap, 'aisles' => $aisles, 'aisleGap_m' => $aisleGap];
            $capacity = $rows * $cols;
            $roomMax = $rowsMax * $colsMax;
        }

        $el['seats'] = $capacity;
        $this->elements[] = $el;

        $this->selectedId = $el['id'];
        $this->persist();

        $label = EventRoom::SEATING_ARRANGEMENTS[$arr][0];
        $per = $capacity > 0 ? round($roomArea / $capacity, 1) : 0;
        $this->showSeatModal = false;
        $short = ! $this->seatFill && $roomMax !== null && $capacity < $target;
        $this->seatMsg = "✓ {$capacity} seats · {$label}"
            .($short ? " (room fits ~{$roomMax})" : '')
            .' · '.$per.' m²/seat';
    }

    /**
     * Table Designer — a U-shape built table by table: a head table with its own
     * chair count, plus arms of standard 180×60cm tables, delegates on the outer
     * edge, open toward the stage. This is manual control, not auto-fit.
     */
    public function designUShape(): void
    {
        $this->validate([
            'uHeadTables' => ['required', 'integer', 'min:1', 'max:12'],
            'uArmTables' => ['required', 'integer', 'min:0', 'max:24'],
            'uPerTable' => ['required', 'integer', 'min:1', 'max:8'],
            'tableW_cm' => ['required', 'numeric', 'min:60', 'max:300'],
            'tableH_cm' => ['required', 'numeric', 'min:40', 'max:120'],
        ]);

        $head = (int) $this->uHeadTables;
        $arm = (int) $this->uArmTables;
        $per = (int) $this->uPerTable;
        $capacity = ($head + 2 * $arm) * $per;

        $el = [
            'id' => Str::random(8), 'type' => 'seatblock', 'arr' => 'ushape', 'mode' => 'utables',
            'rot' => 0, 'x' => 480, 'y' => 310, 'seat_m' => (float) $this->seatSize,
            'headTables' => $head, 'armTables' => $arm, 'perTable' => $per,
            'tableW_m' => (float) $this->tableW_cm / 100, 'tableH_m' => (float) $this->tableH_cm / 100,
            'labels' => $this->seatLabels, 'seats' => $capacity,
        ];
        $this->elements[] = $el;

        $this->selectedId = $el['id'];
        $this->persist();

        $this->showSeatModal = false;
        $tables = $head + 2 * $arm;
        $this->seatMsg = "✓ {$capacity} seats · U-shape · {$tables} tables ({$head} head + {$arm}/arm) · {$this->tableW_cm}×{$this->tableH_cm}cm · {$per} chairs/table";
    }

    public function moveElement(string $id, float $x, float $y): void
    {
        $this->elements = collect($this->elements)->map(function ($el) use ($id, $x, $y) {
            if ($el['id'] === $id) {
                $el['x'] = max(0, min(960, round($x)));
                $el['y'] = max(0, min(560, round($y)));
            }

            return $el;
        })->all();
        $this->persist();
    }

    public function rotate(string $id, int $delta = 45): void
    {
        $this->rotateBy($id, $delta);
    }

    /** Nudge rotation by a delta (degrees), wrapping 0–359. */
    public function rotateBy(string $id, int $delta): void
    {
        $this->elements = collect($this->elements)->map(function ($el) use ($id, $delta) {
            if ($el['id'] === $id) {
                $el['rot'] = (((($el['rot'] ?? 0) + $delta) % 360) + 360) % 360;
            }

            return $el;
        })->all();
        $this->persist();
    }

    /** Set an absolute rotation (degrees) — used by the drag handle and slider. */
    public function setRotation(string $id, $deg): void
    {
        $deg = ((int) round((float) $deg) % 360 + 360) % 360;
        $this->elements = collect($this->elements)->map(function ($el) use ($id, $deg) {
            if ($el['id'] === $id) {
                $el['rot'] = $deg;
            }

            return $el;
        })->all();
        $this->persist();
    }

    public function changeSeats(string $id, int $delta): void
    {
        $this->elements = collect($this->elements)->map(function ($el) use ($id, $delta) {
            if ($el['id'] === $id) {
                $el['seats'] = max(0, ($el['seats'] ?? 0) + $delta);
            }

            return $el;
        })->all();
        $this->persist();
    }

    /**
     * Resize an element. $axis: 'w' | 'h' | 'both' (diameter). Step in px.
     */
    public function resizeElement(string $id, string $axis, int $delta): void
    {
        $this->elements = collect($this->elements)->map(function ($el) use ($id, $axis, $delta) {
            if ($el['id'] === $id) {
                if ($axis === 'w' || $axis === 'both') {
                    $el['w'] = max(20, min(600, ($el['w'] ?? 96) + $delta));
                }
                if ($axis === 'h' || $axis === 'both') {
                    $el['h'] = max(20, min(560, ($el['h'] ?? 96) + $delta));
                }
            }

            return $el;
        })->all();
        $this->persist();
    }

    /** px per metre when room dimensions are set (single scale keeps circles round). */
    private function metreScale(): ?float
    {
        $w = is_numeric($this->width_m) ? (float) $this->width_m : 0;
        $l = is_numeric($this->length_m) ? (float) $this->length_m : 0;
        if ($w <= 0 || $l <= 0) {
            return null;
        }

        return min(960 / $w, 560 / $l);
    }

    /**
     * Set an element's real-world size in metres. $axis: 'w' | 'h' | 'both' (diameter).
     */
    public function setSizeMeters(string $id, string $axis, $metres): void
    {
        $scale = $this->metreScale();
        if (! $scale) {
            return;
        }
        // Keep a small clickable floor without distorting real dimensions.
        $px = max(6, min(920, (int) round(max(0.05, (float) $metres) * $scale)));

        $this->elements = collect($this->elements)->map(function ($el) use ($id, $axis, $px) {
            if ($el['id'] === $id) {
                if ($axis === 'w' || $axis === 'both') {
                    $el['w'] = $px;
                }
                if ($axis === 'h' || $axis === 'both') {
                    $el['h'] = $px;
                }
            }

            return $el;
        })->all();
        $this->persist();
    }

    public function selectAll(): void
    {
        $this->selectedIds = collect($this->elements)->pluck('id')->all();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    /** Toggle one element in the bulk selection (used by the checkbox overlay). */
    public function toggleInSelection(string $id): void
    {
        $this->selectedIds = in_array($id, $this->selectedIds, true)
            ? array_values(array_diff($this->selectedIds, [$id]))
            : [...$this->selectedIds, $id];
    }

    public function deleteSelected(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }
        $this->elements = collect($this->elements)
            ->reject(fn ($el) => in_array($el['id'], $this->selectedIds, true))->values()->all();
        if (in_array($this->selectedId, $this->selectedIds, true)) {
            $this->selectedId = null;
        }
        $this->selectedIds = [];
        $this->persist();
    }

    /**
     * Live-edit a generated seating block from the inspector: change the number
     * of tables, chairs, rows, etc. and the capacity + drawing recompute at once.
     */
    public function updateSeatblock(string $id, string $field, $value): void
    {
        $ints = ['headTables', 'armTables', 'perTable', 'pCols', 'pRows', 'rows', 'cols', 'tRows', 'tCols', 'count'];
        $cm = ['tableW_m', 'tableH_m', 'tableDia_m'];   // arrives in centimetres

        if (! in_array($field, $ints, true) && ! in_array($field, $cm, true)) {
            return;
        }

        $this->elements = collect($this->elements)->map(function ($el) use ($id, $field, $value, $ints) {
            if (($el['id'] ?? '') !== $id || ($el['type'] ?? '') !== 'seatblock') {
                return $el;
            }
            $el[$field] = in_array($field, $ints, true)
                ? max(0, (int) $value)
                : max(0.2, (float) $value / 100);
            $el['seats'] = EventRoom::seatChairs($el, 12)['capacity'];

            return $el;
        })->all();

        $this->persist();
    }

    /** Give any placed item a free-text name shown on the plan. */
    public function nameElement(string $id, string $name): void
    {
        $name = Str::limit(trim($name), 40, '');
        $this->elements = collect($this->elements)->map(function ($el) use ($id, $name) {
            if (($el['id'] ?? '') === $id) {
                $el['name'] = $name ?: null;
            }

            return $el;
        })->all();
        $this->persist();
    }

    public function removeElement(string $id): void
    {
        $this->elements = collect($this->elements)->reject(fn ($el) => $el['id'] === $id)->values()->all();
        if ($this->selectedId === $id) {
            $this->selectedId = null;
        }
        $this->persist();
    }

    public function clearAll(): void
    {
        $this->elements = [];
        $this->selectedId = null;
        $this->selectedIds = [];
        $this->persist();
    }

    public function updatedWidthM(): void
    {
        $this->persist();
    }

    public function updatedLengthM(): void
    {
        $this->persist();
    }

    /** Adjust an equipment item's quantity by $delta (removes the line at 0). */
    public function bumpEquipment(string $item, int $delta): void
    {
        if (! isset($this->equipment[$item])) {
            if ($delta <= 0) {
                return;
            }
            $this->equipment[$item] = ['qty' => 0, 'status' => 'needed', 'notes' => ''];
        }
        $next = max(0, min(999, (int) $this->equipment[$item]['qty'] + $delta));
        if ($next === 0) {
            unset($this->equipment[$item]);
        } else {
            $this->equipment[$item]['qty'] = $next;
        }
        $this->persist();
    }

    /** Toggle an equipment line on (qty 1, needed) / off. */
    public function toggleEquipment(string $item): void
    {
        if (isset($this->equipment[$item])) {
            unset($this->equipment[$item]);
        } else {
            $this->equipment[$item] = ['qty' => 1, 'status' => 'needed', 'notes' => ''];
        }
        $this->persist();
    }

    /** Advance an item through needed → requested → confirmed → onsite → needed. */
    public function cycleStatus(string $item): void
    {
        if (! isset($this->equipment[$item])) {
            return;
        }
        $statuses = EventRoom::EQUIPMENT_STATUSES;
        $i = array_search($this->equipment[$item]['status'] ?? 'needed', $statuses, true);
        $this->equipment[$item]['status'] = $statuses[($i === false ? 0 : $i + 1) % count($statuses)];
        $this->persist();
    }

    public function setNote(string $item, string $note): void
    {
        if (isset($this->equipment[$item])) {
            $this->equipment[$item]['notes'] = trim($note);
            $this->persist();
        }
    }

    public function removeEquipment(string $item): void
    {
        unset($this->equipment[$item]);
        $this->persist();
    }

    /** Merge a preset bundle into the current list (adds to existing quantities). */
    public function applyPackage(string $name): void
    {
        $pkg = EventRoom::EQUIPMENT_PACKAGES[$name] ?? null;
        if (! $pkg) {
            return;
        }
        foreach ($pkg as $item => $qty) {
            if (isset($this->equipment[$item])) {
                $this->equipment[$item]['qty'] = min(999, $this->equipment[$item]['qty'] + $qty);
            } else {
                $this->equipment[$item] = ['qty' => $qty, 'status' => 'needed', 'notes' => ''];
            }
        }
        $this->persist();
    }

    /** Add a free-text custom equipment line. */
    public function addCustomItem(): void
    {
        $name = trim($this->customItem);
        if ($name === '' || isset($this->equipment[$name])) {
            $this->customItem = '';

            return;
        }
        $this->equipment[$name] = ['qty' => 1, 'status' => 'needed', 'notes' => ''];
        $this->customItem = '';
        $this->persist();
    }

    public function clearEquipment(): void
    {
        $this->equipment = [];
        $this->persist();
    }

    // ── Requirements (per-venue; total syncs to the budget) ──
    public function addRequirement(): void
    {
        $this->validate([
            'reqName' => ['required', 'string', 'max:120'],
            'reqCost' => ['nullable', 'numeric', 'min:0'],
            'reqQty' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'reqDays' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $reqs = $this->room->requirements ?? [];
        $reqs[] = [
            'id' => Str::random(8),
            'name' => trim($this->reqName),
            // The price is per unit per day. Twelve microphones for five days is
            // 12 × 5 here rather than a total somebody multiplied in their head —
            // so when the programme shortens, the figure follows.
            'cost_cents' => $this->reqCost !== '' ? (int) round((float) $this->reqCost * 100) : 0,
            'qty' => $this->reqQty !== '' ? (int) $this->reqQty : 1,
            'days' => $this->reqDays !== '' ? (int) $this->reqDays : 1,
        ];
        $this->room->update(['requirements' => $reqs]);
        $this->room->refresh();
        $this->reset(['reqName', 'reqCost', 'reqQty', 'reqDays']);
    }

    public function removeRequirement(string $id): void
    {
        $this->room->update([
            'requirements' => collect($this->room->requirements ?? [])->reject(fn ($r) => ($r['id'] ?? null) === $id)->values()->all(),
        ]);
        $this->room->refresh();
    }

    /** Fill the requirement form from a catalog entry. */
    public function pickReq($catalogId): void
    {
        if ($r = Requirement::find($catalogId)) {
            $this->reqName = $r->name;
            $this->reqCost = $r->unit_price_cents ? (string) ($r->unit_price_cents / 100) : '';
            // Most equipment is hired for as long as the room is held; a shorter
            // run (interpretation on three days of five) is then one edit.
            $this->reqDays = (string) $this->room->chargedDays();
        }
    }

    private function persist(): void
    {
        $this->room->update([
            'layout' => $this->elements,
            'equipment' => $this->equipment,
            'width_m' => is_numeric($this->width_m) && (float) $this->width_m > 0 ? (float) $this->width_m : null,
            'length_m' => is_numeric($this->length_m) && (float) $this->length_m > 0 ? (float) $this->length_m : null,
        ]);
    }

    public function render()
    {
        $selected = $this->selectedId
            ? collect($this->elements)->firstWhere('id', $this->selectedId)
            : null;

        $catalogueItems = collect(EventRoom::EQUIPMENT)->flatten()->all();
        $customItems = array_values(array_diff(array_keys($this->equipment), $catalogueItems));

        $units = collect($this->equipment)->sum(fn ($l) => (int) ($l['qty'] ?? 0));
        $ready = collect($this->equipment)->filter(fn ($l) => in_array($l['status'] ?? '', ['confirmed', 'onsite'], true))->sum(fn ($l) => (int) ($l['qty'] ?? 0));

        return view('livewire.room-layout-builder', [
            'presets' => EventRoom::LAYOUT_PRESETS,
            'equipmentCatalogue' => EventRoom::EQUIPMENT,
            'packages' => EventRoom::EQUIPMENT_PACKAGES,
            'customItems' => $customItems,
            'seatTotal' => collect($this->elements)->sum(fn ($el) => (int) ($el['seats'] ?? 0)),
            'equipmentTotal' => $units,
            'equipmentLines' => count($this->equipment),
            'equipmentReadiness' => $units > 0 ? (int) round($ready / $units * 100) : 0,
            'selected' => $selected,
            'catalog' => Requirement::orderBy('name')->get(),
        ])->layoutData([
            'title' => $this->room->name.' — Room Layout',
            'crumbs' => [
                ['label' => 'Command Center', 'href' => route('home')],
                ['label' => 'Events', 'href' => route('events.index')],
                ['label' => $this->event->name, 'href' => route('events.hub', $this->event)],
                ['label' => 'Venue', 'href' => route('events.hub', [$this->event, 'tab' => 'venue'])],
                ['label' => $this->room->name],
            ],
        ]);
    }
}
