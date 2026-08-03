<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['event_id', 'name', 'type', 'capacity', 'cost_cents', 'days', 'setup_days', 'requirements', 'width_m', 'length_m', 'layout', 'equipment'])]
class EventRoom extends Model
{
    public const TYPES = ['main_hall', 'breakout', 'exhibition', 'registration', 'vip', 'catering'];

    /** Layout element presets: type => [label, seats, w, h, group]. group: seating | staging. */
    public const LAYOUT_PRESETS = [
        'round' => ['Round table', 8, 96, 96, 'seating'],
        'banquet' => ['Banquet (10)', 10, 112, 112, 'seating'],
        'crescent' => ['Half-moon', 6, 120, 66, 'seating'],
        'boardroom' => ['Boardroom', 12, 210, 78, 'seating'],
        'ushape' => ['U-shape', 18, 190, 150, 'seating'],
        'classroom' => ['Classroom', 24, 210, 132, 'seating'],
        'theater' => ['Theater', 30, 200, 116, 'seating'],
        'table' => ['Table', 0, 116, 58, 'seating'],
        'chair' => ['Chair', 1, 28, 28, 'seating'],
        'stage' => ['Stage', 0, 240, 96, 'staging'],
        'screen' => ['Screen', 0, 160, 20, 'staging'],
        'podium' => ['Podium', 0, 44, 44, 'staging'],
        'entrance' => ['Entrance', 0, 60, 22, 'staging'],
        'booth' => ['Translation booth', 0, 64, 64, 'staging'],
    ];

    /** Auto-generated seating styles: key => [label, blurb, family]. */
    public const SEATING_ARRANGEMENTS = [
        'theater' => ['Theater', 'Straight rows of chairs facing the stage — max capacity.', 'grid'],
        'classroom' => ['Classroom', 'Rows of chairs behind desks facing the stage.', 'grid'],
        'banquet' => ['Banquet', 'Full round tables — dining & galas.', 'rounds'],
        'cabaret' => ['Cabaret', 'Half-round tables, open side facing the stage.', 'rounds'],
        'ushape' => ['U-shape', 'Tables in a U, open toward the stage — meetings.', 'perimeter'],
        'boardroom' => ['Boardroom', 'One central table, chairs all around.', 'perimeter'],
        'hollowsquare' => ['Hollow Square', 'A closed square of tables, chairs on the outside.', 'perimeter'],
        'chevron' => ['Herringbone', 'Angled rows (chevron) for better sightlines to the stage.', 'chevron'],
        'circle' => ['Circle', 'Chairs in a ring facing the centre — workshops & dialogue.', 'ring'],
    ];

    /** Legroom presets: key => [label, seat gap m, theater row gap, classroom row gap]. */
    public const SEATING_COMFORT = [
        'compact' => ['Compact', 0.03, 0.30, 0.45],
        'standard' => ['Standard', 0.08, 0.45, 0.60],
        'roomy' => ['Roomy', 0.14, 0.65, 0.80],
    ];

    /** Equipment catalogue by category. */
    public const EQUIPMENT = [
        'Audio' => ['Sound system (PA)', 'Handheld microphones', 'Lapel / lavalier mics', 'Delegate microphones', 'Podium microphone', 'Audio mixer', 'Stage monitors'],
        'Translation' => ['Interpreter booths', 'Translation receivers / headsets', 'IR transmitters'],
        'Visual' => ['Projector', 'Projection screen', 'LED / video wall', 'Confidence monitors', 'Presentation laptop', 'Clicker / presenter remote'],
        'Lighting' => ['Stage lighting', 'Ambient / uplighting', 'Spotlights', 'Follow spot'],
        'Staging' => ['Stage / platform', 'Podium / lectern', 'Backdrop', 'Truss rigging'],
        'Branding' => ['Banners', 'Roll-up stands', 'Directional signage', 'Branded backdrop'],
        'Connectivity' => ['Wi-Fi access points', 'Extension cables', 'Power distribution', 'Cable management'],
    ];

    /** Preparation lifecycle for each equipment line. Order = progression. */
    public const EQUIPMENT_STATUSES = ['needed', 'requested', 'confirmed', 'onsite'];

    /** One-click bundles: package name => [item => qty]. */
    public const EQUIPMENT_PACKAGES = [
        'Conference' => ['Sound system (PA)' => 1, 'Handheld microphones' => 2, 'Lapel / lavalier mics' => 2, 'Projector' => 1, 'Projection screen' => 1, 'Presentation laptop' => 1, 'Clicker / presenter remote' => 1],
        'Summit + Translation' => ['Sound system (PA)' => 1, 'Delegate microphones' => 12, 'Interpreter booths' => 2, 'Translation receivers / headsets' => 80, 'IR transmitters' => 4, 'LED / video wall' => 1, 'Confidence monitors' => 2],
        'Gala / Awards' => ['Sound system (PA)' => 1, 'Stage lighting' => 1, 'Spotlights' => 2, 'Follow spot' => 1, 'Handheld microphones' => 2, 'LED / video wall' => 1, 'Branded backdrop' => 1, 'Truss rigging' => 1],
        'Exhibition' => ['Wi-Fi access points' => 4, 'Power distribution' => 2, 'Directional signage' => 6, 'Roll-up stands' => 4, 'Banners' => 4, 'Ambient / uplighting' => 1],
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'equipment' => 'array',
            'requirements' => 'array',
            'cost_cents' => 'integer',
            'days' => 'integer',
            'setup_days' => 'integer',
            'width_m' => 'float',
            'length_m' => 'float',
        ];
    }

    /**
     * Every requirement row carries an id, whatever wrote it.
     *
     * The id is what the remove button, and any future edit, matches on. A row
     * that arrives without one — from an import, a script, a hand-edited JSON
     * column — used to take the whole venue screen down on render. Stamping it
     * here means there is one place that guarantees it rather than every reader
     * guarding against it.
     */
    protected function requirements(): Attribute
    {
        return Attribute::set(fn ($value) => json_encode(
            collect($value ?? [])->values()->map(fn ($row, $i) => array_merge(
                is_array($row) ? $row : ['name' => (string) $row],
                ['id' => (string) (($row['id'] ?? null) ?: Str::random(8))],
            ))->all()
        ));
    }

    /**
     * How many days this room is used, counted off the programme.
     *
     * The agenda already knows: a session carries the room it is in and the day
     * it is on. Asking somebody to type a number the platform can count is how
     * the number ends up disagreeing with the schedule after it moves.
     */
    public function daysOnTheAgenda(): int
    {
        return $this->sessions()
            ->distinct()->count('agenda_day_id');
    }

    /**
     * The days this room is charged for.
     *
     * The agenda's count, unless somebody has said otherwise — a dark day held
     * between two meetings is still paid for, and a room booked before the
     * programme exists has no sessions to count.
     */
    public function chargedDays(): int
    {
        $days = $this->days ?? $this->daysOnTheAgenda();

        return max(1, $days) + max(0, (int) $this->setup_days);
    }

    /** Whether the charged days are the platform's count or somebody's override. */
    public function daysAreCounted(): bool
    {
        return $this->days === null;
    }

    /** Hire: the day rate for as many days as it is held. */
    public function hireCents(): int
    {
        return (int) ($this->cost_cents ?? 0) * $this->chargedDays();
    }

    /**
     * One requirement's cost: the rate, times how many, times how many days.
     *
     * Every row used to be a single figure, so twelve microphones for five days
     * was one number somebody multiplied in their head — and nothing could be
     * recalculated when the programme moved. Missing quantity or days count as
     * one, so a row written before this still totals what it always did.
     */
    public static function requirementCents(array $row): int
    {
        $rate = (int) ($row['cost_cents'] ?? 0);
        $qty = max(1, (int) ($row['qty'] ?? 1));
        $days = max(1, (int) ($row['days'] ?? 1));

        return $rate * $qty * $days;
    }

    /** Sum of this venue's costed requirements (cents). */
    public function requirementsTotalCents(): int
    {
        return collect($this->requirements ?? [])->sum(fn ($r) => self::requirementCents($r));
    }

    /** Full venue cost = hire for its days + all requirements (cents). */
    public function totalCents(): int
    {
        return $this->hireCents() + $this->requirementsTotalCents();
    }

    /** Total seats across all placed elements. */
    public function seatCount(): int
    {
        return collect($this->layout ?? [])->sum(fn ($el) => (int) ($el['seats'] ?? 0));
    }

    /**
     * Chair/table geometry for a generated seating block, in px relative to the
     * block's centre (y increases toward the back of the room; the stage is up).
     * Returns chairs[[x,y]], tables[[x,y,dia]], desks[[x,y,w,h]], w, h, capacity, chairPx.
     */
    /** Row index → spreadsheet-style letters (0=A, 26=AA). */
    public static function rowLetters(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $i--;
            $s = chr(65 + $i % 26).$s;
            $i = intdiv($i, 26);
        }

        return $s;
    }

    public static function seatChairs(array $el, float $scale): array
    {
        $arr = $el['arr'] ?? 'theater';
        $seat = max(0.2, (float) ($el['seat_m'] ?? 0.6));
        $chairs = [];
        $tables = [];
        $desks = [];
        $rects = [];
        $labels = [];

        // ── Table-designed U-shape: a head table plus arms of discrete rectangular
        //    tables (180×60cm by default), delegates seated on the outer edge,
        //    open toward the room. Driven by the Table Designer, not auto-fit. ──
        if (($el['mode'] ?? '') === 'utables') {
            // A U built from discrete tables: a head row of N tables and two arms
            // of N tables, every table the same size, each seating `perTable`
            // chairs on its outer edge. Everything is centred on the block origin.
            $tW = max(0.6, (float) ($el['tableW_m'] ?? 1.8));   // table long side
            $tH = max(0.4, (float) ($el['tableH_m'] ?? 0.6));   // table depth
            $headT = max(1, (int) ($el['headTables'] ?? 2));    // tables across the head
            $arm = max(0, (int) ($el['armTables'] ?? 3));       // tables per side arm
            $perTable = max(1, (int) ($el['perTable'] ?? 3));   // chairs per table
            $off = $tH / 2 + $seat / 2 + 0.08;                  // chair gap from a table edge

            // Chair x/y offsets that spread `perTable` seats along a table's length.
            $spread = fn ($centre, $len) => $perTable > 1
                ? array_map(fn ($k) => $centre - $len / 2 + $seat / 2 + $k * (($len - $seat) / ($perTable - 1)), range(0, $perTable - 1))
                : [$centre];

            $headW = $headT * $tW;                               // head row spans its tables
            $armLen = $arm * $tW;
            $totalH = $tH + $armLen;
            $topY = -$totalH / 2 + $tH / 2;                      // head-row centre-line
            $leftX = -$headW / 2 + $tH / 2;                      // arm centre-lines
            $rightX = $headW / 2 - $tH / 2;

            // head: a row of tables, chairs along the outer (top) edge of each
            $n = 0;
            for ($t = 0; $t < $headT; $t++) {
                $cx = -$headW / 2 + $tW / 2 + $t * $tW;
                $rects[] = [$cx, $topY, $tW, $tH];
                foreach ($spread($cx, $tW) as $x) {
                    $chairs[] = [$x, $topY - $off, 'H'.(++$n)];
                }
            }

            // arms: a stack of tables down each side, chairs on the outer edge
            $n = 0;
            for ($t = 0; $t < $arm; $t++) {
                $cy = $topY + $tH / 2 + $t * $tW + $tW / 2;
                $rects[] = [$leftX, $cy, $tH, $tW];
                $rects[] = [$rightX, $cy, $tH, $tW];
                foreach ($spread($cy, $tW) as $y) {
                    $chairs[] = [$leftX - $off, $y, 'L'.(++$n)];
                    $chairs[] = [$rightX + $off, $y, 'R'.$n];
                }
            }

            $capacity = ($headT + 2 * $arm) * $perTable;
        } elseif (in_array($arr, ['ushape', 'boardroom', 'hollowsquare'], true)) {
            $pitch = $seat + (float) ($el['colGap_m'] ?? 0.10);
            $cols = max(1, (int) ($el['pCols'] ?? 6));   // seats along a horizontal run
            $rows = max(1, (int) ($el['pRows'] ?? 4));   // seats along a vertical run
            $band = 0.75;                                 // table depth
            $off = $band / 2 + $seat / 2 + 0.08;          // chair distance from table centre-line
            $tableW = max($pitch, ($cols - 1) * $pitch);
            $tableL = max($pitch, ($rows - 1) * $pitch);
            $hx = $tableW / 2;
            $hy = $tableL / 2;
            $open = $arr === 'ushape'; // only the U opens the stage (top) side

            $n = 0;
            for ($i = 0; $i < $cols; $i++) {
                $x = -$hx + $i * $pitch;
                if (! $open) {
                    $chairs[] = [$x, -($hy + $off), (string) ++$n]; // top
                }
                $chairs[] = [$x, $hy + $off, (string) ++$n];        // bottom
            }
            for ($j = 0; $j < $rows; $j++) {
                $y = -$hy + $j * $pitch;
                $chairs[] = [-($hx + $off), $y, (string) ++$n]; // left
                $chairs[] = [$hx + $off, $y, (string) ++$n];    // right
            }

            if ($arr === 'boardroom') {
                $rects[] = [0, 0, $tableW + $band, $tableL + $band]; // solid table
            } else {
                $rects[] = [0, $hy, $tableW + $band, $band];  // bottom band
                $rects[] = [-$hx, 0, $band, $tableL + $band]; // left band
                $rects[] = [$hx, 0, $band, $tableL + $band];  // right band
                if ($arr === 'hollowsquare') {
                    $rects[] = [0, -$hy, $tableW + $band, $band]; // top band closes the square
                }
            }
            $capacity = count($chairs);
        } elseif ($arr === 'chevron') {
            $rows = max(1, (int) ($el['rows'] ?? 1));
            $cols = max(2, (int) ($el['cols'] ?? 2));
            $colPitch = $seat + (float) ($el['colGap_m'] ?? 0.10);
            $rowPitch = $seat + (float) ($el['rowGap_m'] ?? 0.45);
            $aisleGap = (float) ($el['aisleGap_m'] ?? 1.4);
            $ang = deg2rad((float) ($el['angle'] ?? 12));
            $half = intdiv($cols, 2);
            $rot = fn ($x, $y, $a) => [$x * cos($a) - $y * sin($a), $x * sin($a) + $y * cos($a)];
            for ($r = 0; $r < $rows; $r++) {
                $y = $r * $rowPitch;
                $sn = 0;
                for ($i = 0; $i < $half; $i++) {           // left wing (fans left toward the back)
                    [$rx, $ry] = $rot(-($aisleGap / 2) - ($half - 1 - $i) * $colPitch, $y, $ang);
                    $chairs[] = [$rx, $ry, self::rowLetters($r).(++$sn)];
                }
                for ($i = 0; $i < $cols - $half; $i++) {   // right wing
                    [$rx, $ry] = $rot(($aisleGap / 2) + $i * $colPitch, $y, -$ang);
                    $chairs[] = [$rx, $ry, self::rowLetters($r).(++$sn)];
                }
            }
            $capacity = $rows * $cols;
        } elseif ($arr === 'circle') {
            $seatPitch = $seat + (float) ($el['colGap_m'] ?? 0.15);
            $ringGap = max($seatPitch, (float) ($el['rowGap_m'] ?? 0.8));
            $want = max(1, (int) ($el['count'] ?? 12));
            $remaining = $want;
            $radius = 1.2;
            $guard = 0;
            $sn = 0;
            while ($remaining > 0 && $guard++ < 60) {
                $ncap = max(3, (int) floor(2 * M_PI * $radius / $seatPitch));
                $put = min($remaining, $ncap);
                for ($k = 0; $k < $put; $k++) {
                    $a = 2 * M_PI * $k / $put - M_PI / 2;
                    $chairs[] = [cos($a) * $radius, sin($a) * $radius, (string) ++$sn];
                }
                $remaining -= $put;
                $radius += $ringGap;
            }
            $capacity = count($chairs);
        } elseif (in_array($arr, ['banquet', 'cabaret'], true)) {
            $dia = max(0.8, (float) ($el['tableDia_m'] ?? 1.8));
            $per = max(2, (int) ($el['perTable'] ?? ($arr === 'cabaret' ? 6 : 10)));
            $tRows = max(1, (int) ($el['tRows'] ?? 1));
            $tCols = max(1, (int) ($el['tCols'] ?? 1));
            $pitch = $dia + (float) ($el['tableGap_m'] ?? 1.4);
            $ring = $dia / 2 + $seat / 2 + 0.08;
            for ($tr = 0; $tr < $tRows; $tr++) {
                for ($tc = 0; $tc < $tCols; $tc++) {
                    $tx = $tc * $pitch;
                    $ty = $tr * $pitch;
                    $tables[] = [$tx, $ty, $dia];
                    $tno = $tr * $tCols + $tc + 1;
                    for ($k = 0; $k < $per; $k++) {
                        $deg = $arr === 'cabaret' ? 300 + $k * (300 / max(1, $per - 1)) : $k * 360 / $per;
                        $rad = deg2rad($deg);
                        $chairs[] = [$tx + cos($rad) * $ring, $ty + sin($rad) * $ring, $tno.'-'.($k + 1)];
                    }
                }
            }
            $capacity = $tRows * $tCols * $per;
        } else {
            $rows = max(1, (int) ($el['rows'] ?? 1));
            $cols = max(1, (int) ($el['cols'] ?? 1));
            $classroom = $arr === 'classroom';
            $colGap = (float) ($el['colGap_m'] ?? 0.10);
            $rowGap = (float) ($el['rowGap_m'] ?? ($classroom ? 0.60 : 0.45));
            $deskDepth = $classroom ? 0.45 : 0;
            $aisles = (int) ($el['aisles'] ?? (! empty($el['aisle']) ? 1 : 0));
            $aisleGap = (float) ($el['aisleGap_m'] ?? 1.2);
            $colPitch = $seat + $colGap;
            $rowPitch = $seat + $deskDepth + $rowGap;
            $wantLabels = ! empty($el['labels']);

            $breaks = [];
            for ($k = 1; $k <= $aisles; $k++) {
                $breaks[] = (int) round($k * $cols / ($aisles + 1));
            }
            $xOf = function ($c) use ($colPitch, $breaks, $aisleGap) {
                $shift = 0;
                foreach ($breaks as $b) {
                    if ($c >= $b) {
                        $shift += $aisleGap;
                    }
                }

                return $c * $colPitch + $shift;
            };

            for ($r = 0; $r < $rows; $r++) {
                $y = $r * $rowPitch;
                for ($c = 0; $c < $cols; $c++) {
                    $chairs[] = [$xOf($c), $y, self::rowLetters($r).($c + 1)];
                }
                if ($classroom) {
                    $desks[] = [$xOf($cols - 1) / 2, $y - $seat / 2 - 0.05 - $deskDepth / 2, $xOf($cols - 1) + $seat, $deskDepth];
                }
                if ($wantLabels) {
                    $labels[] = [-$seat, $y, self::rowLetters($r)];
                }
            }
            $capacity = $rows * $cols;
        }

        // Bounding box (metres) → recentre everything on the box centre.
        $xs = [];
        $ys = [];
        foreach ($chairs as [$x, $y]) {
            $xs[] = $x - $seat / 2;
            $xs[] = $x + $seat / 2;
            $ys[] = $y - $seat / 2;
            $ys[] = $y + $seat / 2;
        }
        foreach ($tables as [$x, $y, $d]) {
            $xs[] = $x - $d / 2;
            $xs[] = $x + $d / 2;
            $ys[] = $y - $d / 2;
            $ys[] = $y + $d / 2;
        }
        foreach (array_merge($desks, $rects) as [$x, $y, $w, $h]) {
            $xs[] = $x - $w / 2;
            $xs[] = $x + $w / 2;
            $ys[] = $y - $h / 2;
            $ys[] = $y + $h / 2;
        }
        if (empty($xs)) {
            return ['chairs' => [], 'tables' => [], 'desks' => [], 'rects' => [], 'labels' => [], 'w' => 10, 'h' => 10, 'capacity' => 0, 'chairPx' => max(4, $seat * $scale)];
        }
        $cx = (min($xs) + max($xs)) / 2;
        $cy = (min($ys) + max($ys)) / 2;
        $mk = fn ($x, $y) => [round(($x - $cx) * $scale, 1), round(($y - $cy) * $scale, 1)];

        return [
            'chairs' => array_map(fn ($c) => [...$mk($c[0], $c[1]), $c[2] ?? ''], $chairs),
            'tables' => array_map(fn ($t) => [...$mk($t[0], $t[1]), round($t[2] * $scale, 1)], $tables),
            'desks' => array_map(fn ($d) => [...$mk($d[0], $d[1]), round($d[2] * $scale, 1), round($d[3] * $scale, 1)], $desks),
            'rects' => array_map(fn ($r) => [...$mk($r[0], $r[1]), round($r[2] * $scale, 1), round($r[3] * $scale, 1)], $rects),
            'labels' => array_map(fn ($l) => [...$mk($l[0], $l[1]), $l[2]], $labels),
            'w' => round((max($xs) - min($xs)) * $scale, 1),
            'h' => round((max($ys) - min($ys)) * $scale, 1),
            'capacity' => $capacity,
            'chairPx' => max(4.0, round($seat * $scale, 1)),
        ];
    }

    /**
     * Equipment lines: item => ['qty'=>int, 'status'=>string, 'notes'=>string].
     * Normalises legacy int-only values ({item: qty}).
     */
    public function equipmentLines(): Collection
    {
        return collect($this->equipment ?? [])->map(function ($line) {
            if (is_array($line)) {
                return [
                    'qty' => max(0, (int) ($line['qty'] ?? 0)),
                    'status' => in_array($line['status'] ?? '', self::EQUIPMENT_STATUSES, true) ? $line['status'] : 'needed',
                    'notes' => (string) ($line['notes'] ?? ''),
                ];
            }

            return ['qty' => max(0, (int) $line), 'status' => 'needed', 'notes' => ''];
        })->filter(fn ($l) => $l['qty'] > 0);
    }

    /** Total equipment units requested. */
    public function equipmentCount(): int
    {
        return $this->equipmentLines()->sum('qty');
    }

    /** Share of units that are confirmed or on-site (0–100). */
    public function equipmentReadiness(): int
    {
        $lines = $this->equipmentLines();
        $total = $lines->sum('qty');
        if ($total === 0) {
            return 0;
        }
        $ready = $lines->whereIn('status', ['confirmed', 'onsite'])->sum('qty');

        return (int) round($ready / $total * 100);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventAgendaSession::class, 'room_id');
    }
}
