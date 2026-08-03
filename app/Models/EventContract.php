<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\ContractAppendices;
use App\Support\ContractClauses;
use App\Support\ContractTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A generated contract or letter for an event — the client Management Services
 * Agreement, a vendor agreement, a speaker or sponsorship deal, or a letter.
 *
 * `data` holds the editable content (parties, figures, bilingual clause blocks);
 * the standard clause text is seeded from App\Support\ContractClauses. An event
 * has many of these, one per counterparty. `type` decides the shape.
 */
class EventContract extends Model
{
    use Auditable;

    /** The `data` JSON autosaves constantly — only real decisions are logged. */
    public const AUDIT_FIELDS = ['status', 'signed_at', 'version', 'type'];

    /**
     * The document types this generator produces. Each declares its counterparty
     * model (null = parties live in `data`, or a free-text recipient), its default
     * language, and whether it carries the structured client fields (cost-share
     * parties, budget assumptions, payment schedule) or just editable clauses.
     *
     * @var array<string,array{label:string,party:?class-string,language:string,structured:bool}>
     */
    public const TYPES = [
        'client' => ['label' => 'Client Contract', 'party' => null, 'language' => 'bilingual', 'structured' => true],
        'vendor' => ['label' => 'Vendor Agreement', 'party' => Supplier::class, 'language' => 'en', 'structured' => false],
        'speaker' => ['label' => 'Speaker Agreement', 'party' => EventSpeaker::class, 'language' => 'en', 'structured' => false],
        'sponsorship' => ['label' => 'Sponsorship Agreement', 'party' => EventSponsor::class, 'language' => 'en', 'structured' => false],
        'letter' => ['label' => 'Letter', 'party' => null, 'language' => 'en', 'structured' => false],
        // The document that closes a project. Signed after the Event, not at
        // the start, and its signature is what makes the final payment due —
        // so it is a document of its own with its own status, not a clause.
        'acceptance' => ['label' => 'Certificate of Services', 'party' => null, 'language' => 'bilingual', 'structured' => false],
    ];

    public const STATUSES = ['draft', 'sent', 'partially_signed', 'signed', 'void'];

    /**
     * Label + badge class per status, one definition shared by the register
     * and the document editor — they showed the same status in two different
     * colours (brand gold vs semantic amber) before this existed.
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function statusMeta(): array
    {
        $class = [
            'draft' => 'bg-navy-50 text-navy-500',
            'sent' => 'bg-amber-100 text-amber-700',
            'partially_signed' => 'bg-amber-100 text-amber-700',
            'signed' => 'bg-emerald-100 text-emerald-700',
            'void' => 'bg-navy-50 text-navy-400',
        ];

        return collect(self::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => [
                \App\Support\Workflow::label('contract_status', $status),
                $class[$status] ?? 'bg-navy-50 text-navy-500',
            ]])->all();
    }

    protected $fillable = ['event_id', 'type', 'party_type', 'party_id', 'title', 'language',
        'template_key', 'reference', 'status', 'version', 'data', 'signed_at'];

    protected $casts = [
        'data' => 'array',
        'signed_at' => 'datetime',
    ];

    /**
     * The currency this document is written in — always the event's.
     *
     * It used to be copied into the document's own JSON when the document was
     * created, and every reader then had its own `?? 'JOD'` fallback. So a
     * contract raised before the event's currency was set stayed on the
     * fallback for ever, and the same screen could show the value in JOD and
     * the figure pulled from the budget in dollars.
     *
     * Money on a contract is the event's money. One place says so.
     */
    public function currencyCode(): string
    {
        return $this->event?->currency ?: CompanyProfile::currency();
    }

    /** Format a cents amount the way the rest of the platform formats money. */
    public function money(?int $cents): string
    {
        return Event::moneyIn($cents, $this->currencyCode());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** The counterparty — a Supplier, EventSpeaker or EventSponsor. */
    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? 'Contract';
    }

    public function isClient(): bool
    {
        return $this->type === 'client';
    }

    public function isLetter(): bool
    {
        return $this->type === 'letter';
    }

    /** Client contracts show the structured parties / assumptions / schedule cards. */
    public function isStructured(): bool
    {
        return (bool) (self::TYPES[$this->type]['structured'] ?? false);
    }

    public function isBilingual(): bool
    {
        return $this->language === 'bilingual';
    }

    /** Lifecycle stage for the deck meter: 0 draft · 1 in-progress · 2 signed. */
    public function stageIndex(): int
    {
        return match ($this->status) {
            'signed' => 2,
            'sent', 'partially_signed' => 1,
            default => 0,   // draft, void
        };
    }

    /** Which pipeline column a contract sits in. */
    public function pipelineColumn(): string
    {
        return match ($this->status) {
            'signed' => 'signed',
            'sent', 'partially_signed' => 'sent',
            'void' => 'void',
            default => 'draft',
        };
    }

    /** The name shown in the contracts list — its title, or the counterparty. */
    public function displayTitle(): string
    {
        return $this->title
            ?: ($this->party?->name
                ? $this->typeLabel().' · '.$this->party->name
                : $this->typeLabel());
    }

    /** The client Management Services Agreement — the original, kept as type=client. */
    public static function forEvent(Event $event): self
    {
        return static::firstOrCreate(
            ['event_id' => $event->id, 'type' => 'client'],
            [
                'reference' => static::nextReference($event, 'client'),
                'language' => 'bilingual',
                'status' => 'draft',
                'version' => '1.0',
                'data' => static::defaultData($event),
            ],
        );
    }

    /**
     * Open a new contract of any type, optionally tied to a counterparty. The
     * counterparty's name pre-fills the title so the list reads cleanly.
     */
    public static function createFor(Event $event, string $type, ?Model $party = null): self
    {
        $type = isset(self::TYPES[$type]) ? $type : 'letter';

        return static::create([
            'event_id' => $event->id,
            'type' => $type,
            'party_type' => $party ? $party->getMorphClass() : null,
            'party_id' => $party?->getKey(),
            'title' => $party?->name ? self::TYPES[$type]['label'].' · '.$party->name : self::TYPES[$type]['label'],
            'language' => self::TYPES[$type]['language'],
            'reference' => static::nextReference($event, $type),
            'status' => 'draft',
            'version' => '1.0',
            'data' => static::defaultData($event, $type, $party),
        ]);
    }

    /** Three-letter reference tag per type. Client keeps its original CTR. */
    private const REF_TAGS = [
        'client' => 'CTR', 'vendor' => 'VEN', 'speaker' => 'SPK',
        'sponsorship' => 'SPN', 'letter' => 'LTR', 'acceptance' => 'ACT',
    ];

    /** "EBH-CTR-2026-007" — unique per event; a suffix keeps a second one distinct. */
    private static function nextReference(Event $event, string $type): string
    {
        $seq = static::where('event_id', $event->id)->count() + 1;
        $tag = self::REF_TAGS[$type] ?? 'DOC';

        return 'EBH-'.$tag.'-'.now()->format('Y').'-'.str_pad((string) $event->id, 3, '0', STR_PAD_LEFT)
            .($seq > 1 ? '-'.$seq : '');
    }

    public static function defaultData(Event $event, string $type = 'client', ?Model $party = null): array
    {
        // Non-client documents start lean: a title, the event context, the
        // counterparty, and an empty body the planner fills. The rich clause
        // libraries per type arrive with the template phase.
        if ($type !== 'client') {
            $dates = $event->starts_at && $event->ends_at
                ? $event->starts_at->format('j').'–'.$event->ends_at->format('j M Y')
                : ($event->starts_at?->format('j M Y') ?? 'To be confirmed');
            $location = trim(($event->city ? $event->city.', ' : '').($event->country ?? 'Jordan'), ', ');

            $base = [
                'meta' => [
                    'title_en' => self::TYPES[$type]['label'].($party?->name ? ' — '.$party->name : ''),
                    'title_ar' => '',
                    'place' => $location ?: 'Amman, Jordan',
                    'date' => now()->format('j F Y'),
                ],
                'currency' => $event->currency ?: CompanyProfile::currency(),
                'event' => [
                    'name' => $event->name,
                    'dates' => $dates,
                    'venue' => $event->venue?->name ?? 'To be confirmed',
                    'location' => $location ?: 'Amman, Jordan',
                ],
                'counterparty' => [
                    'name_en' => $party?->name ?? '',
                    'email' => $party->email ?? null,
                    'phone' => $party->phone ?? null,
                    // A speaker carries a fee and topic, a sponsor an amount and a
                    // package, a supplier a category — the template interpolates
                    // whichever exists.
                    'fee_cents' => $party->fee_cents ?? $party->amount_cents ?? null,
                    'package' => $party->package ?? null,
                    'detail' => $party->topic ?? $party->category ?? null,
                ],
            ];

            // The Act closes the client contract, so it inherits that contract's
            // parties and its acceptance window rather than asking for them
            // again — a Certificate naming a different Client than the
            // agreement it settles would be worthless.
            if ($type === 'acceptance') {
                $agreement = static::where('event_id', $event->id)->where('type', 'client')->first();
                $base['meta']['title_en'] = 'Certificate of Services Rendered';
                $base['meta']['title_ar'] = 'محضر إنجاز الخدمات';
                $base['second_parties'] = $agreement->data['second_parties'] ?? [];
                $base['terms'] = $agreement->data['terms'] ?? ['acceptance_days' => 5];
                $base['financials'] = ['currency' => $event->currency ?: CompanyProfile::currency()];
            }

            // The type's standard clauses, seeded once — the contract owns them after.
            // Generated text quotes a figure, so it has to be generated in
            // the event's currency rather than whatever the seed left behind.
            $base['currency'] = $event->currency ?: CompanyProfile::currency();
            $base['financials']['currency'] = $base['currency'];
            $base['blocks'] = ContractTemplates::blocks($type, $base);

            return $base;
        }

        // The same figure the "From budget" button pulls in later — the
        // priced budget lines, not $event->budget_cents (a manually-typed cap
        // that is usually unset, which is why a fresh contract used to open
        // saying the project is worth nothing).
        $forecast = $event->costForecast();
        $estimated = $forecast['forecast'] ?: $forecast['cap'];
        $dates = $event->starts_at && $event->ends_at
            ? $event->starts_at->format('j').'–'.$event->ends_at->format('j M Y')
            : ($event->starts_at?->format('j M Y') ?? 'To be confirmed');
        $location = trim(($event->city ? $event->city.', ' : '').($event->country ?? 'Jordan'), ', ');

        return [
            'meta' => [
                'title_en' => 'Event Management Services Agreement',
                'title_ar' => 'اتفاقية خدمات إدارة الفعاليات',
                'place' => $location ?: 'Amman, Jordan',
                'date' => now()->format('j F Y'),
                'confidentiality' => 'Confidential',
            ],
            'event' => [
                'name' => $event->name,
                'dates' => $dates,
                'venue' => $event->venue?->name ?? 'To be confirmed',
                'location' => $location ?: 'Amman, Jordan',
            ],
            'first_party' => [
                'name_en' => 'Al Sattam for Exhibitions, Conferences & Consulting Services, trading as Elite Business Hub',
                'name_ar' => 'شركة السطام للمعارض والمؤتمرات والخدمات الاستشارية، وتعمل تحت الاسم التجاري إيليت بزنس هَب',
                'rep_en' => ($event->projectManager?->name ?? 'Emran Aletan').' — General Manager & CEO',
                'rep_ar' => 'المدير العام والرئيس التنفيذي',
            ],
            // The Second Party — this event's client, and only that.
            //
            // Two parties is what a contract is. A second funding entity is a
            // real thing that happens, but it is something you add on purpose:
            // seeding one meant every agreement opened with a party nobody had
            // named, a share nobody had agreed and a third signature block on
            // the last page. Add a Second Party in the editor and the split —
            // and its signature line — appear with it.
            'second_parties' => [
                [
                    'name_en' => $event->client?->name ?? 'The Client',
                    // No client record carries its Arabic name, and inventing
                    // one puts a different organisation's name on the page.
                    'name_ar' => '',
                    'share' => 100,
                ],
            ],
            'financials' => [
                'currency' => $event->currency ?: CompanyProfile::currency(),
                // The contract stands on its own figure. The budget can seed it
                // once (Pull from budget) but never drives it afterwards.
                'value_mode' => 'fixed',                 // fixed | estimate
                'contract_value_cents' => $estimated,
                'estimated_total_cents' => $estimated,   // legacy, kept for old contracts
                'management_fee_pct' => (float) ($event->management_fee_pct ?? 15),
                // Percent → milestone, on the house terms: signature, then 60,
                // 30 and 7 days out. Fully settled before anyone travels.
                'payment_schedule' => [
                    ['pct' => 15, 'when_en' => 'Upon execution of this Agreement', 'when_ar' => 'عند توقيع هذه الاتفاقية'],
                    ['pct' => 30, 'when_en' => 'No later than sixty (60) days prior to the Event', 'when_ar' => 'قبل الفعالية بمدة لا تقل عن ستين (60) يوماً'],
                    ['pct' => 30, 'when_en' => 'No later than thirty (30) days prior to the Event', 'when_ar' => 'قبل الفعالية بمدة لا تقل عن ثلاثين (30) يوماً'],
                    ['pct' => 25, 'when_en' => 'No later than seven (7) days prior to the Event', 'when_ar' => 'قبل الفعالية بمدة لا تقل عن سبعة (7) أيام'],
                ],
            ],
            // The rates and periods the articles quote. Editing one here rewrites
            // the sentence that cites it — nobody edits legal text to change 16%.
            'terms' => [
                'hotel_tax_pct' => 8,
                'hotel_service_pct' => 7,
                'vat_pct' => 16,
                'permits_pct' => 10,
                'cure_days' => 14,
                'acceptance_days' => 5,
            ],
            'assumptions' => [
                'attendees_min' => 500,
                'attendees_max' => 650,
                'catering_en' => 'two coffee breaks and lunch per day',
                'catering_ar' => 'استراحتَي قهوة ووجبة غداء يومياً',
                'rooms' => 150,
                'nights' => 3,
            ],
        ];
    }

    /**
     * The contract body: an ordered list of editable bilingual blocks.
     * Seeded from the standard clause set the first time it's read, then owned
     * by this contract — editing a block never touches any other contract.
     */
    public function blocks(): array
    {
        $blocks = $this->data['blocks'] ?? null;

        return is_array($blocks) && $blocks !== []
            ? $blocks
            : ContractClauses::blocks($this->data ?? []);
    }

    /**
     * The annexes, in the order they are bound.
     *
     * Each is {slug, title_en, title_ar, source, pulled_at, blocks} — and its
     * blocks are the same shape as the body's, so the paper renders an appendix
     * with the code that already renders a clause.
     *
     * The slug is the identity: clause text refers to an appendix by slug, not
     * by number, so reordering them renumbers every reference instead of
     * quietly making one wrong.
     *
     * @return list<array<string,mixed>>
     */
    public function appendices(): array
    {
        $list = $this->data['appendices'] ?? null;

        if (! is_array($list)) {
            return $this->isClient() ? ContractAppendices::seed() : [];
        }

        return array_values($list);
    }

    /** slug => "1", for resolving {{appendix:slug}} references. */
    public function appendixNumbers(): array
    {
        $n = [];
        foreach ($this->appendices() as $i => $a) {
            $n[$a['slug'] ?? ''] = (string) ($i + 1);
        }

        return $n;
    }

    /** The agreed figure the payment schedule is calculated against (cents). */
    public function valueCents(): int
    {
        $f = $this->data['financials'] ?? [];

        return (int) ($f['contract_value_cents'] ?? $f['estimated_total_cents'] ?? 0);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EventContractPayment::class, 'contract_id')->orderBy('sort');
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(ContractSignatory::class, 'contract_id')->orderBy('order')->orderBy('id');
    }

    /**
     * The default parties who sign this type — the organiser always, plus the
     * counterparty (or, on a client contract, each second party). Idempotent.
     */
    public function ensureSignatories(): void
    {
        if ($this->signatories()->exists()) {
            return;
        }

        $organiser = CompanyProfile::first()?->name ?? 'Elite Business Hub';
        $rows = [['role' => 'organiser', 'name' => $organiser, 'order' => 0]];

        if ($this->isClient() || $this->type === 'acceptance') {
            // The Act closes the client contract, so the same parties sign it —
            // and it reads the Client from the same place, which means a party
            // added to the contract appears here too.
            $parties = ContractClauses::parties($this->data ?? []) ?: ($this->data['second_parties'] ?? []);
            foreach (array_values($parties) as $i => $sp) {
                $rows[] = ['role' => 'client', 'name' => $sp['name_en'] ?? 'Client', 'order' => $i + 1];
            }
        } elseif ($this->type !== 'letter') {
            $rows[] = [
                'role' => match ($this->type) {
                    'vendor' => 'vendor', 'speaker' => 'speaker', 'sponsorship' => 'sponsor', default => 'client',
                },
                'name' => $this->party?->name ?? ($this->data['counterparty']['name_en'] ?? 'Counterparty'),
                'email' => $this->party->email ?? ($this->data['counterparty']['email'] ?? null),
                'order' => 1,
            ];
        }

        foreach ($rows as $r) {
            $this->signatories()->create($r);
        }
    }

    /**
     * Keep the client's signature lines in step with the Second Parties.
     *
     * The signature page is the parties, restated. A block for somebody who is
     * not named on page one — or a party named there with nowhere to sign — is
     * how a contract goes out wrong, and the two lists drifted apart the moment
     * a funder was added or dropped after the contract was first opened.
     *
     * Only unsigned lines are touched. A signature is a record of something
     * that happened, and nothing here may quietly erase one. Names are left
     * alone too: a line may carry the representative rather than the entity.
     */
    public function syncClientSignatories(): void
    {
        if (! ($this->isClient() || $this->type === 'acceptance')) {
            return;
        }

        if (! $this->signatories()->exists()) {
            return;   // nothing seeded yet; ensureSignatories does the first pass
        }

        $parties = collect(ContractClauses::parties($this->data ?? []))->pluck('name_en')->filter()->values();
        $lines = $this->signatories()->where('role', 'client')->get();

        foreach ($parties->slice($lines->count()) as $i => $name) {
            $this->signatories()->create([
                'role' => 'client',
                'name' => $name,
                'order' => (int) $this->signatories()->max('order') + 1,
            ]);
        }

        foreach ($lines->slice($parties->count()) as $surplus) {
            if (! $surplus->isSigned()) {
                $surplus->delete();
            }
        }
    }

    /** sha256 of the content each signatory attests to — "what they signed". */
    public function contentHash(): string
    {
        return hash('sha256', json_encode($this->data));
    }

    public function signedCount(): int
    {
        return $this->signatories->whereNotNull('signed_at')->count();
    }

    public function signatoryCount(): int
    {
        return $this->signatories->count();
    }

    public function isFullySigned(): bool
    {
        $total = $this->signatoryCount();

        return $total > 0 && $this->signedCount() === $total;
    }

    /**
     * Derive the contract's status from its signatures — the authoritative flow.
     * None signed keeps sent/draft; some → partially_signed; all → signed.
     */
    public function syncStatusFromSignatures(): void
    {
        $total = $this->signatories()->count();
        if ($total === 0) {
            return;
        }

        $signed = $this->signatories()->whereNotNull('signed_at')->count();

        if ($signed === $total) {
            $this->status = 'signed';
            $this->signed_at = $this->signed_at ?? now();
        } elseif ($signed > 0) {
            $this->status = 'partially_signed';
            $this->signed_at = null;
        } elseif (in_array($this->status, ['signed', 'partially_signed'], true)) {
            // Every signature was undone — fall back to "sent", not "draft".
            $this->status = 'sent';
            $this->signed_at = null;
        }

        $this->save();
    }

    /**
     * Materialise the contract's payment schedule as trackable installments.
     * Idempotent — existing rows are never touched, so recorded money survives.
     *
     * Default due dates follow the standard EBH schedule shape: on signing,
     * then 60 / 30 / 7 days before the event.
     */
    public function ensurePayments(): void
    {
        if ($this->payments()->exists()) {
            return;
        }

        $schedule = $this->data['financials']['payment_schedule'] ?? [];
        $total = $this->data['financials']['estimated_total_cents'] ?? 0;
        $starts = $this->event?->starts_at;
        $offsets = count($schedule) === 4 ? [null, 60, 30, 7] : [];

        $rows = array_values($schedule);
        $last = count($rows) - 1;
        $run = 0;

        foreach ($rows as $i => $s) {
            $due = null;
            if (array_key_exists($i, $offsets)) {
                $due = $offsets[$i] === null
                    ? now()->toDateString()
                    : $starts?->copy()->subDays($offsets[$i])->toDateString();
            }

            // The last installment takes the remainder rather than its own
            // rounded share. Rounding each share independently does not add up:
            // 15/30/30/25 of JD 525,000.55 comes to a cent MORE than the
            // contract, and of JD 1,000.01 to a cent less — so the client
            // settles every installment and the agreement still does not
            // reconcile. The final row absorbs it, as it does on paper.
            $amount = $i === $last
                ? (int) $total - $run
                : (int) round($total * (float) ($s['pct'] ?? 0) / 100);
            $run += $amount;

            $this->payments()->create([
                'event_id' => $this->event_id,
                'sort' => $i,
                'label' => $s['when_en'] ?? ('Installment '.($i + 1)),
                'pct' => (float) ($s['pct'] ?? 0),
                'amount_cents' => $amount,
                'due_on' => $due,
            ]);
        }
    }

    /**
     * Re-price unpaid installments after the schedule or the estimate changed.
     * Rows with money on them are never rewritten — cash received is history.
     */
    public function repriceUnpaidPayments(): void
    {
        $schedule = array_values($this->data['financials']['payment_schedule'] ?? []);
        $total = (int) ($this->data['financials']['estimated_total_cents'] ?? 0);

        // Cash received is history, so a row with money on it keeps its figure
        // and what is left to schedule is the total less those.
        [$settled, $open] = $this->payments()->get()
            ->partition(fn (EventContractPayment $p) => $p->paid_cents > 0);

        $remaining = $total - (int) $settled->sum('amount_cents');

        $open = $open->filter(fn (EventContractPayment $p) => isset($schedule[$p->sort]))->values();
        $last = $open->count() - 1;
        $run = 0;

        foreach ($open as $i => $payment) {
            $row = $schedule[$payment->sort];

            // The final unpaid row takes the remainder, so the schedule adds up
            // to the contract however the percentages round.
            $amount = $i === $last
                ? $remaining - $run
                : (int) round($total * (float) ($row['pct'] ?? 0) / 100);
            $run += $amount;

            $payment->update([
                'label' => $row['when_en'] ?? $payment->label,
                'pct' => (float) ($row['pct'] ?? $payment->pct),
                'amount_cents' => $amount,
            ]);
        }
    }

    public function slug(): string
    {
        return Str::slug($this->event->name).'-agreement';
    }

    public function auditLabel(): string
    {
        return 'Contract '.$this->reference;
    }
}
