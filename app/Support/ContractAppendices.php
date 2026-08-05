<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\EventContract;
use App\Models\EventInvoiceItem;

/**
 * The annexes: what a contract can carry behind its signatures.
 *
 * An appendix is {slug, title_en, title_ar, source, pulled_at, blocks}, and its
 * blocks are the same shape as the body's — so the paper renders an appendix
 * with the code that already renders a clause, and the editor edits one with
 * the editor that already edits the body.
 *
 * The slug is the identity. Clause text refers to an appendix as
 * {{appendix:scope}}, never as "Appendix 1", so reordering them renumbers every
 * reference in the document instead of quietly making one of them wrong.
 *
 * Pulling from a module takes a SNAPSHOT. A contract that changes after it is
 * sent because somebody edited a budget line is not a contract. Refreshing is a
 * deliberate act, and the platform refuses it once the document is signed.
 */
class ContractAppendices
{
    /**
     * The library offered on "Add appendix". Each entry knows where its content
     * comes from; 'blank' and 'scope' start from typed text.
     */
    public const LIBRARY = [
        'scope' => ['Detailed Scope of Work', 'نطاق العمل التفصيلي', 'brief'],
        'budget' => ['Estimated Budget', 'الموازنة التقديرية', 'budget'],
        'programme' => ['Event Programme', 'برنامج الفعالية', 'agenda'],
        'venue' => ['Venue and Technical Requirements', 'متطلبات القاعات والتجهيزات الفنية', 'venue'],
        'pricing' => ['Price List', 'قائمة الأسعار', 'pricing'],
        'acceptance' => ['Certificate of Services Rendered', 'محضر إنجاز الخدمات', 'form'],
        'blank' => ['Untitled Appendix', 'ملحق بلا عنوان', 'typed'],
    ];

    /**
     * What every new client contract is bound with: one appendix, the scope.
     * Everything else is a deliberate addition — a line-item budget attached to
     * a fixed-price contract invites a renegotiation nobody asked for.
     *
     * @return list<array<string,mixed>>
     */
    public static function seed(): array
    {
        return [self::make('scope')];
    }

    /**
     * An empty appendix of the given kind, ready to be filled or pulled into.
     */
    public static function make(string $key, ?string $slug = null): array
    {
        [$en, $ar, $source] = self::LIBRARY[$key] ?? self::LIBRARY['blank'];

        return [
            'slug' => $slug ?: $key,
            'title_en' => $en,
            'title_ar' => $ar,
            // The source is what a pull reads, and what the editor badges. It is
            // kept for every kind — 'typed' and 'form' simply have nothing to
            // pull from, which pull() decides, not this.
            'source' => $source,
            'pulled_at' => null,
            'blocks' => $key === 'acceptance' ? self::acceptanceForm() : [],
        ];
    }

    /**
     * Pull an appendix's content from the module that owns it.
     *
     * @return array{0:list<array<string,mixed>>,1:string} blocks and a summary
     */
    public static function pull(string $source, Event $event, EventContract $contract): array
    {
        return match ($source) {
            'budget' => self::fromBudget($event, $contract),
            'agenda' => self::fromAgenda($event),
            'venue' => self::fromVenue($event),
            'pricing' => self::fromPricing($event),
            'brief' => self::fromBrief($event),
            default => [[], 'Nothing to pull'],
        };
    }

    /**
     * The budget, as the client sees it.
     *
     * Every figure here is what the client is CHARGED — sellCents(), the one
     * definition of that number in the platform. Cost never appears in a
     * document that leaves the building, and this is the only place that
     * decision is made, so it cannot be undone by a checkbox somewhere.
     */
    private static function fromBudget(Event $event, EventContract $contract): array
    {
        $cur = $event->currency ?: \App\Models\CompanyProfile::currency();
        $fee = (float) ($event->management_fee_pct ?? 15);
        $money = fn (int $c) => \App\Support\Money::forDocument($c, $cur);

        $items = $event->budgetItems()->with('event')->get()
            ->filter(fn (EventBudgetItem $i) => $i->sellCents($fee) > 0)
            ->groupBy(fn (EventBudgetItem $i) => $i->category ?: 'Other');

        if ($items->isEmpty()) {
            return [[], 'The budget has no billable lines yet'];
        }

        $blocks = [];
        $total = 0;
        $n = 0;

        foreach ($items as $category => $lines) {
            $sub = $lines->sum(fn (EventBudgetItem $i) => $i->sellCents($fee));
            $total += $sub;
            $n++;

            $blocks[] = [
                'id' => 'ax'.$n,
                'type' => 'bullets',
                'title_en' => (string) $category,
                // A budget category has no Arabic name. Repeating the English
                // one in the Arabic column reads as a mistake, not a
                // translation — the column stays empty instead.
                'title_ar' => '',
                'en' => ['Subtotal: '.$money($sub)],
                'ar' => ['المجموع الفرعي: '.$money($sub)],
                'items' => $lines->map(fn (EventBudgetItem $i) => [
                    'l_en' => $i->description ?: 'Line item',
                    'l_ar' => '',
                    't_en' => trim(($i->quantity > 1 ? $i->quantity.' × ' : '').$money($i->sellCents($fee))),
                    't_ar' => '',
                ])->values()->all(),
                'rows' => [],
            ];
        }

        $blocks[] = [
            'id' => 'ax'.($n + 1),
            'type' => 'prose',
            'title_en' => 'Total',
            'title_ar' => 'الإجمالي',
            'en' => ['Total estimated budget: '.$money($total).'. All figures are exclusive of the taxes and government fees set out in the Agreement.'],
            'ar' => ['إجمالي الموازنة التقديرية: '.$money($total).'. وجميع المبالغ غير شاملة للضرائب والرسوم الحكومية المنصوص عليها في الاتفاقية.'],
            'items' => [], 'rows' => [],
        ];

        return [$blocks, $items->flatten()->count().' lines · '.$money($total)];
    }

    /**
     * The event's own price list — what it will invoice at, one rate per
     * unit. Unlike the budget appendix this carries no total: a rate card
     * lists what a room or a transfer costs per unit, not what this event is
     * committed to buying of it — summing "one of everything priced" would
     * read as a bill nobody agreed to.
     *
     * Only what the event actually sells, never what it costs — an invoice
     * item's sell price is the only figure a client-facing document shows.
     */
    private static function fromPricing(Event $event): array
    {
        $cur = $event->currency ?: \App\Models\CompanyProfile::currency();
        $money = fn (int $c) => \App\Support\Money::forDocument($c, $cur);

        $items = $event->invoiceItems()->active()->orderBy('category')->orderBy('sort')->get()
            ->groupBy(fn (EventInvoiceItem $i) => $i->category ?: 'Uncategorised');

        if ($items->isEmpty()) {
            return [[], 'The price list has no active items yet'];
        }

        $blocks = [[
            'id' => 'ax0',
            'type' => 'prose',
            'title_en' => 'Rates',
            'title_ar' => 'الأسعار',
            'en' => ['Rates below are per unit, as priced for this event. The amount invoiced depends on the quantities and services actually used.'],
            'ar' => ['الأسعار أدناه لكل وحدة، كما تم تسعيرها لهذه الفعالية. ويعتمد المبلغ المفوتَر على الكميات والخدمات المستخدمة فعليًا.'],
            'items' => [], 'rows' => [],
        ]];

        foreach ($items as $category => $lines) {
            $blocks[] = [
                'id' => 'ax'.(count($blocks) + 1),
                'type' => 'bullets',
                'title_en' => (string) $category,
                'title_ar' => '',
                'en' => [],
                'ar' => [],
                'items' => $lines->map(fn (EventInvoiceItem $i) => [
                    'l_en' => $i->name.($i->code ? ' ('.$i->code.')' : ''),
                    'l_ar' => '',
                    't_en' => $money($i->sell_cents).' '.mb_strtolower($i->unitLabel()),
                    't_ar' => '',
                ])->values()->all(),
                'rows' => [],
            ];
        }

        return [$blocks, $items->flatten()->count().' items'];
    }

    /** The programme, day by day, as it stands in the Agenda. */
    private static function fromAgenda(Event $event): array
    {
        $days = $event->agendaDays()->with(['sessions' => fn ($q) => $q->orderBy('starts_at')->orderBy('sort')])->orderBy('sort')->get();

        if ($days->isEmpty()) {
            return [[], 'The agenda has no days yet'];
        }

        $blocks = [];
        $sessions = 0;

        foreach ($days as $n => $day) {
            $label = trim(($day->date?->format('l, j F Y') ?? '').($day->label ? ' — '.$day->label : ''), ' —');
            $sessions += $day->sessions->count();

            $blocks[] = [
                'id' => 'ax'.($n + 1),
                'type' => 'bullets',
                'title_en' => $label ?: 'Day '.($n + 1),
                // Only the word "Day" translates; an English date beside itself
                // in the Arabic column would read as a mistake.
                'title_ar' => 'اليوم '.self::arabicNumeral((string) ($n + 1)),
                'en' => [], 'ar' => [],
                // Session times are stored as plain "09:00" strings, not
                // datetimes — they belong to the day, not to a calendar.
                'items' => $day->sessions->map(fn ($s) => [
                    'l_en' => trim(self::clock($s->starts_at).($s->ends_at ? '–'.self::clock($s->ends_at) : '').'  '.$s->title),
                    'l_ar' => '',
                    't_en' => collect([$s->room?->name, $s->speaker])->filter()->implode(' · '),
                    't_ar' => '',
                ])->values()->all(),
                'rows' => [],
            ];
        }

        return [$blocks, $days->count().' days · '.$sessions.' sessions'];
    }

    /** Rooms, capacities, layouts and what each one needs. */
    private static function fromVenue(Event $event): array
    {
        $rooms = $event->rooms()->orderBy('name')->get();

        if ($rooms->isEmpty()) {
            return [[], 'No rooms have been set up yet'];
        }

        return [[[
            'id' => 'ax1',
            'type' => 'bullets',
            'title_en' => 'Rooms and Capacities',
            'title_ar' => 'القاعات والسعات',
            'en' => ['The Contractor shall coordinate with the venue on the following spaces:'],
            'ar' => ['يتولّى المتعهّد التنسيق مع القاعة بشأن المساحات التالية:'],
            'items' => $rooms->map(fn ($r) => [
                'l_en' => $r->name,
                'l_ar' => '',
                't_en' => collect([
                    $r->capacity ? $r->capacity.' persons' : null,
                    $r->type ? str($r->type)->replace('_', ' ')->title() : null,
                    collect($r->requirements ?? [])->pluck('name')->filter()->implode(', ') ?: null,
                ])->filter()->implode(' · '),
                't_ar' => '',
            ])->values()->all(),
            'rows' => [],
        ]], $rooms->count().' rooms'];
    }

    /** The scope, from the Brief's own scope and components sections. */
    private static function fromBrief(Event $event): array
    {
        $brief = $event->brief;
        $data = $brief?->data ?? [];

        $rows = collect($data['components'] ?? [])
            ->filter(fn ($r) => trim((string) ($r['area'] ?? '')) !== '');

        if ($rows->isEmpty()) {
            return [[], 'The Brief has no components yet'];
        }

        return [[[
            'id' => 'ax1',
            'type' => 'bullets',
            'title_en' => 'Event Components',
            'title_ar' => 'مكوّنات الفعالية',
            'en' => ['The Event comprises the following components, each delivered within the Scope of Services set out in the Agreement:'],
            'ar' => ['تتألّف الفعالية من المكوّنات التالية:'],
            'items' => $rows->map(fn ($r) => [
                'l_en' => (string) $r['area'],
                'l_ar' => '',
                't_en' => (string) ($r['detail'] ?? $r['notes'] ?? ''),
                't_ar' => '',
            ])->values()->all(),
            'rows' => [],
        ]], $rows->count().' components'];
    }

    /**
     * Resolve {{appendix:slug}} in a piece of clause text.
     *
     * Clause text never contains a number. It names the appendix by slug, and
     * the number is worked out here from the appendix order — so dragging
     * Appendix 3 above Appendix 1 renumbers every sentence that mentions
     * either, instead of leaving one of them wrong in a signed document.
     *
     * A reference to an appendix that no longer exists renders loudly rather
     * than silently: the preview shows it, and the export refuses.
     */
    public static function resolve(string $text, array $numbers, string $lang = 'en'): string
    {
        return preg_replace_callback('/\{\{\s*appendix:([a-z0-9_-]+)\s*\}\}/i', function ($m) use ($numbers, $lang) {
            $n = $numbers[$m[1]] ?? null;

            if ($n === null) {
                return $lang === 'ar' ? '⚠ ملحق محذوف' : '⚠ MISSING APPENDIX';
            }

            return $lang === 'ar' ? 'الملحق '.self::arabicNumeral($n) : 'Appendix '.$n;
        }, $text) ?? $text;
    }

    /** Every slug a document's text refers to, whether or not it exists. */
    public static function referencedSlugs(array $blocks): array
    {
        $found = [];

        array_walk_recursive($blocks, function ($v) use (&$found) {
            if (is_string($v) && preg_match_all('/\{\{\s*appendix:([a-z0-9_-]+)\s*\}\}/i', $v, $m)) {
                $found = [...$found, ...$m[1]];
            }
        });

        return array_values(array_unique($found));
    }

    /**
     * References pointing at nothing. A document with one of these must not be
     * exported — a wrong cross-reference in a signed contract is worse than a
     * missing feature.
     */
    public static function brokenReferences(array $blocks, array $numbers): array
    {
        return array_values(array_diff(self::referencedSlugs($blocks), array_keys($numbers)));
    }

    /** "09:00" from whatever the agenda holds — a string, or a Carbon. */
    private static function clock(mixed $t): string
    {
        return match (true) {
            $t instanceof \DateTimeInterface => $t->format('H:i'),
            is_string($t) && $t !== '' => substr($t, 0, 5),
            default => '',
        };
    }

    private static function arabicNumeral(string $n): string
    {
        return strtr($n, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }

    /**
     * The Act — the form the Client signs to accept the services, referenced by
     * the Acceptance of Services article. Blank by design: it is completed at
     * the end of the Event, not at signature.
     */
    private static function acceptanceForm(): array
    {
        return [[
            'id' => 'ax1',
            'type' => 'prose',
            'title_en' => 'Certificate of Services Rendered',
            'title_ar' => 'محضر إنجاز الخدمات',
            'en' => [
                'This Certificate is issued under the Agreement and records the services delivered by Elite Business Hub for the Event.',
                'Period covered: from ____________ to ____________.',
                'The services listed in the Agreement and its appendices were rendered in full, on time, and to the quality required. The Client confirms it has no claim as to their scope, quality or timing.',
                'Total value of services rendered: ____________________.  Amounts previously paid: ____________________.  Balance now due: ____________________.',
            ],
            'ar' => [
                'صدر هذا المحضر بموجب الاتفاقية، ويوثّق الخدمات التي نفّذتها إيليت بزنس هَب للفعالية.',
                'الفترة المشمولة: من ____________ إلى ____________.',
                'نُفِّذت الخدمات الواردة في الاتفاقية وملاحقها بالكامل وفي مواعيدها وبالجودة المطلوبة، ويقرّ العميل بعدم وجود أي مطالبة تتعلّق بنطاقها أو جودتها أو توقيتها.',
                'إجمالي قيمة الخدمات المنفَّذة: ____________________.  المبالغ المدفوعة سابقاً: ____________________.  الرصيد المستحقّ الآن: ____________________.',
            ],
            'items' => [], 'rows' => [],
        ]];
    }
}
