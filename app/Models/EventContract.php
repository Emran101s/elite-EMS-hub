<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Event Management Services Agreement — the signable, bilingual (EN/AR) contract
 * between Elite Business Hub (First Party) and the client entities (Second Party).
 *
 * `data` holds only the variables (parties, cost split, payment schedule, budget
 * assumptions); the legal clause text is standard and lives in App\Support\ContractClauses.
 */
class EventContract extends Model
{
    use \App\Models\Concerns\Auditable;

    /** The `data` JSON autosaves constantly — only real decisions are logged. */
    public const AUDIT_FIELDS = ['status', 'signed_at', 'version'];

    protected $fillable = ['event_id', 'reference', 'status', 'version', 'data', 'signed_at'];

    protected $casts = [
        'data' => 'array',
        'signed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public static function forEvent(Event $event): self
    {
        return static::firstOrCreate(
            ['event_id' => $event->id],
            [
                'reference' => 'EBH-CTR-'.now()->format('Y').'-'.str_pad((string) $event->id, 3, '0', STR_PAD_LEFT),
                'status' => 'draft',
                'version' => '1.0',
                'data' => static::defaultData($event),
            ],
        );
    }

    public static function defaultData(Event $event): array
    {
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
                'estimated_total_cents' => $estimated,
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

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventContractPayment::class, 'contract_id')->orderBy('sort');
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
                'amount_cents' => (int) round($total * ($s['pct'] ?? 0) / 100),
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
                'amount_cents' => (int) round($total * ($row['pct'] ?? 0) / 100),
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
