<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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
    ];

    public const STATUSES = ['draft', 'sent', 'partially_signed', 'signed', 'void'];

    protected $fillable = ['event_id', 'type', 'party_type', 'party_id', 'title', 'language',
        'template_key', 'reference', 'status', 'version', 'data', 'signed_at'];

    protected $casts = [
        'data' => 'array',
        'signed_at' => 'datetime',
    ];

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
        'sponsorship' => 'SPN', 'letter' => 'LTR',
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
                'currency' => $event->currency ?? 'JOD',
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

            // The type's standard clauses, seeded once — the contract owns them after.
            $base['blocks'] = ContractTemplates::blocks($type, $base);

            return $base;
        }

        $estimated = (int) ($event->budget_cents ?? 0);
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
            // The Second Party — the client, split across the two funding entities.
            'second_parties' => [
                ['name_en' => $event->client?->name ?? 'World People Assembly', 'name_ar' => 'الجمعية العالمية للشعوب', 'share' => 80],
                ['name_en' => 'Peace Group', 'name_ar' => 'مجموعة السلام', 'share' => 20],
            ],
            'financials' => [
                'currency' => $event->currency ?? 'JOD',
                // The contract stands on its own figure. The budget can seed it
                // once (Pull from budget) but never drives it afterwards.
                'value_mode' => 'fixed',                 // fixed | estimate
                'contract_value_cents' => $estimated,
                'estimated_total_cents' => $estimated,   // legacy, kept for old contracts
                'management_fee_pct' => (float) ($event->management_fee_pct ?? 15),
                // Percent → milestone. Full amount settled by one week before.
                'payment_schedule' => [
                    ['pct' => 30, 'when_en' => 'Upon signing of this Agreement', 'when_ar' => 'عند توقيع هذه الاتفاقية'],
                    ['pct' => 30, 'when_en' => 'No later than sixty (60) days before the Event', 'when_ar' => 'قبل الفعالية بمدة لا تقل عن ستين (60) يوماً'],
                    ['pct' => 20, 'when_en' => 'No later than thirty (30) days before the Event', 'when_ar' => 'قبل الفعالية بمدة لا تقل عن ثلاثين (30) يوماً'],
                    ['pct' => 20, 'when_en' => 'The balance, so the full amount is settled no later than one (1) week before the Event', 'when_ar' => 'الرصيد المتبقّي بحيث يُسدَّد كامل المبلغ قبل بدء الفعالية بأسبوع واحد (1) على الأقل'],
                ],
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

        if ($this->isClient()) {
            foreach (array_values($this->data['second_parties'] ?? []) as $i => $sp) {
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

        foreach (array_values($schedule) as $i => $s) {
            $due = null;
            if (array_key_exists($i, $offsets)) {
                $due = $offsets[$i] === null
                    ? now()->toDateString()
                    : $starts?->copy()->subDays($offsets[$i])->toDateString();
            }

            $this->payments()->create([
                'event_id' => $this->event_id,
                'sort' => $i,
                'label' => $s['when_en'] ?? ('Installment '.($i + 1)),
                'pct' => (float) ($s['pct'] ?? 0),
                'amount_cents' => (int) round($total * (float) ($s['pct'] ?? 0) / 100),
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
        $total = $this->data['financials']['estimated_total_cents'] ?? 0;

        foreach ($this->payments()->get() as $payment) {
            $row = $schedule[$payment->sort] ?? null;
            if (! $row || $payment->paid_cents > 0) {
                continue;
            }
            $payment->update([
                'label' => $row['when_en'] ?? $payment->label,
                'pct' => (float) ($row['pct'] ?? $payment->pct),
                'amount_cents' => (int) round($total * (float) ($row['pct'] ?? 0) / 100),
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
