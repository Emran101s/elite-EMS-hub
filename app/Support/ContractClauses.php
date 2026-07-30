<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Number;

/**
 * The house Event Management Services Agreement, in English and Arabic.
 *
 * This is the template, not one signed deal. It follows the nineteen-article
 * structure Elite Business Hub actually executes on, and every figure, party,
 * date and percentage in it is interpolated from the contract's stored data —
 * so the same wording serves a JOD 350,000 summit and a JOD 12,000 workshop
 * without anyone editing legal text to change a number.
 *
 * The English is the drafted text; the Arabic is its counterpart, set beside it
 * clause for clause. Once seeded, both are ordinary editable blocks — a lawyer
 * can rewrite any of it in the editor without touching this file.
 *
 * What is customisable, and where it comes from:
 *
 *   parties, representatives   data.first_party / data.second_parties
 *   the event, dates, venue    data.event (written from the Event record)
 *   value + fixed vs estimate  data.financials.contract_value_cents / value_mode
 *   payment milestones         data.financials.payment_schedule
 *   cost split between funders data.second_parties[].share
 *   tax rates, notice period   data.terms
 *   which language governs     data.meta.prevailing_language
 */
class ContractClauses
{
    /**
     * The Client, as the document should name it.
     *
     * A party is a row with an English name. Not because English matters more,
     * but because the operative recital — "Second Party (Client): …" — is built
     * from name_en, and Article 23 makes the English text the controlling one.
     * A row with only an Arabic name cannot be named in the sentence that binds
     * it, so it is an unfinished form row, not a second Client.
     *
     * That is also what somebody means when they clear the name to remove a
     * party: it must stop appearing, not linger as "· 0%".
     *
     * @return list<array<string,mixed>>
     */
    public static function parties(array $d): array
    {
        return array_values(array_filter(
            $d['second_parties'] ?? [],
            fn ($p) => trim((string) ($p['name_en'] ?? '')) !== '',
        ));
    }

    /**
     * Whether a share means anything.
     *
     * A percentage exists to divide a cost between funders. One Client pays all
     * of it, so "100%" tells the reader nothing and "0%" is simply wrong —
     * neither belongs on the page. Shares appear only when there is a split.
     */
    public static function sharesApply(array $d): bool
    {
        return count(self::parties($d)) > 1;
    }

    /** Recitals / parties block shown before the numbered clauses. */
    public static function recitals(array $d): array
    {
        $sp = collect(self::parties($d))->pluck('name_en')->filter()->join(' and ');
        $spAr = collect(self::parties($d))->pluck('name_ar')->filter()->join(' و');
        $ev = $d['event'] ?? [];

        // Latin runs inside an Arabic sentence — a place, a date, an event named
        // in English — are reordered by the bidirectional algorithm and break
        // across lines mid-phrase. Isolating each one keeps it whole and in the
        // right place, which is what U+2068/U+2069 exist for.
        // A place and a date are short and must never break: "22 July 2026"
        // split across two lines of Arabic reads as two different things.
        $place = self::isolate($d['meta']['place'] ?? '', nowrap: true);
        $date = self::isolate($d['meta']['date'] ?? '', nowrap: true);
        $evNameAr = self::isolate($ev['name'] ?? '');
        $evDatesAr = self::isolate($ev['dates'] ?? '');
        $evVenueAr = self::isolate($ev['venue'] ?? '');
        $evLocationAr = self::isolate($ev['location'] ?? '');
        $spAr = self::isolate($spAr);

        return [
            'en' => [
                "This Event Management Services Agreement (the “Agreement”) is entered into in {$d['meta']['place']} on {$d['meta']['date']} by and between:",
                "First Party (Contractor): {$d['first_party']['name_en']}, represented by {$d['first_party']['rep_en']}, hereinafter referred to as the “Contractor” or “Elite Business Hub”; and",
                "Second Party (Client): {$sp}, hereinafter referred to as the “Client”.",
                'The Contractor and the Client are hereinafter collectively referred to as the “Parties” and individually as a “Party”.',
                "Whereas the Client wishes to organise “{$ev['name']}” ({$ev['dates']}, {$ev['venue']}, {$ev['location']}), and the Contractor agrees to plan, manage, supervise and deliver the said event on a full-service basis from project inception through final completion and post-event reporting; the Parties have agreed as follows:",
            ],
            'ar' => [
                "أُبرمت اتفاقية خدمات إدارة الفعاليات هذه (\"الاتفاقية\") في {$place} بتاريخ {$date} بين كلٍّ من:",
                "الطرف الأول (المتعهّد): {$d['first_party']['name_ar']}، ويمثّلها {$d['first_party']['rep_ar']}، ويُشار إليها فيما بعد بـ \"المتعهّد\" أو \"إيليت بزنس هَب\"؛",
                "الطرف الثاني (العميل): {$spAr}، ويُشار إليه فيما بعد بـ \"العميل\".",
                'ويُشار إلى المتعهّد والعميل مجتمعَين بـ "الطرفين" وإلى كلٍّ منهما منفرداً بـ "الطرف".',
                "وحيث إن العميل يرغب في تنظيم فعالية \"{$evNameAr}\" ({$evDatesAr}، {$evVenueAr}، {$evLocationAr})، وحيث يوافق المتعهّد على تخطيط الفعالية المذكورة وإدارتها والإشراف عليها وتنفيذها بنظام الخدمة الشاملة، من بدء المشروع وحتى إتمامه النهائي ورفع التقرير الختامي؛ فقد اتفق الطرفان على ما يلي:",
            ],
        ];
    }

    /**
     * The nineteen articles, in the order they are executed in.
     *
     * @return list<array<string,mixed>>
     */
    public static function clauses(array $d): array
    {
        $f = $d['financials'] ?? [];
        $t = $d['terms'] ?? [];
        $sp = self::parties($d);
        $cur = $f['currency'] ?? 'JOD';

        // The contract stands on its own agreed figure, not on a live budget estimate.
        $valueCents = (int) ($f['contract_value_cents'] ?? $f['estimated_total_cents'] ?? 0);
        $money = $cur.' '.number_format($valueCents / 100, 2);
        // "JOD 350,000.00" reads as a foreign body inside an Arabic sentence;
        // the Arabic side names the currency in Arabic and keeps the figure.
        $moneyAr = number_format($valueCents / 100, 2).' '.self::arabicCurrency($cur);
        [$wordsEn, $wordsAr] = self::inWords($valueCents, $cur);
        $isFixed = ($f['value_mode'] ?? 'fixed') === 'fixed';

        $accept = (int) ($t['acceptance_days'] ?? 5);
        $acceptAr = self::arabicNumeral($accept);

        return array_values(array_filter([

            // ── 1 · SCOPE OF SERVICES ────────────────────────────────────────
            [
                'n' => '1', 'type' => 'bullets',
                'en_title' => 'Scope of Services', 'ar_title' => 'نطاق الخدمات',
                'en' => [
                    'Elite Business Hub shall serve as the exclusive event management company, the sole coordination authority, and the single point of contact for the Event. The Contractor shall be responsible for the planning, management, supervision, coordination and full execution of the Event from project inception through final completion and post-event reporting.',
                    'The scope of services shall include, but not be limited to, the following:',
                ],
                'ar' => [
                    'تعمل إيليت بزنس هَب بصفتها الشركة الحصرية لإدارة الفعالية، والجهة الوحيدة المخوّلة بالتنسيق، ونقطة الاتصال الموحّدة للفعالية. ويكون المتعهّد مسؤولاً عن تخطيط الفعالية وإدارتها والإشراف عليها وتنسيقها وتنفيذها الكامل، من بدء المشروع وحتى إتمامه النهائي ورفع التقرير الختامي.',
                    'ويشمل نطاق الخدمات، على سبيل المثال لا الحصر، ما يلي:',
                ],
                // The deliverables list — edit, reorder or delete freely per event.
                'items' => [
                    ['l_en' => 'Full project management', 'l_ar' => 'الإدارة الكاملة للمشروع', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Development of operational and implementation plans', 'l_ar' => 'إعداد الخطط التشغيلية والتنفيذية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Concept development and visual identity design', 'l_ar' => 'تطوير الفكرة وتصميم الهوية البصرية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Management of plenary sessions, panel discussions and roundtables', 'l_ar' => 'إدارة الجلسات العامة وحلقات النقاش والموائد المستديرة', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Management of the accompanying exhibition', 'l_ar' => 'إدارة المعرض المصاحب', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Coordination of VIP guests and international delegations', 'l_ar' => 'تنسيق كبار الضيوف والوفود الدولية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Registration and accreditation management', 'l_ar' => 'إدارة التسجيل والاعتماد', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Hotel and accommodation management', 'l_ar' => 'إدارة الفنادق والإقامة', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Transportation and logistics management', 'l_ar' => 'إدارة النقل والخدمات اللوجستية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Catering and hospitality coordination', 'l_ar' => 'تنسيق التغذية والضيافة', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Interpretation and translation services', 'l_ar' => 'خدمات الترجمة الفورية والتحريرية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Livestreaming and digital platform management', 'l_ar' => 'البث المباشر وإدارة المنصّة الرقمية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Stage production and technical management', 'l_ar' => 'إنتاج المسرح والإدارة الفنية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Security coordination and governmental permits', 'l_ar' => 'التنسيق الأمني والتصاريح الحكومية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Vendor and subcontractor management', 'l_ar' => 'إدارة المورّدين والمتعاقدين من الباطن', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Photography, videography, media relations and event coverage', 'l_ar' => 'التصوير الفوتوغرافي والفيديو والعلاقات الإعلامية وتغطية الفعالية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Opening, closing and awards ceremony management', 'l_ar' => 'إدارة حفلات الافتتاح والختام وتوزيع الجوائز', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Preparation and submission of the final event report', 'l_ar' => 'إعداد التقرير النهائي للفعالية وتقديمه', 't_en' => '', 't_ar' => ''],
                ],
            ],

            // ── 2 · EXCLUSIONS ───────────────────────────────────────────────
            [
                'n' => '2', 'type' => 'bullets',
                'en_title' => 'Exclusions', 'ar_title' => 'الاستثناءات',
                'en' => ['Unless expressly included in the approved Scope of Work or its associated appendices, the following shall be excluded from this Agreement:'],
                'ar' => ['ما لم يُدرَج صراحةً في نطاق العمل المعتمَد أو ملاحقه، يُستثنى ما يلي من هذه الاتفاقية:'],
                'items' => [
                    ['l_en' => 'International airfare and travel tickets', 'l_ar' => 'تذاكر السفر والطيران الدولية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Visa fees and embassy charges', 'l_ar' => 'رسوم التأشيرات ورسوم السفارات', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Third-party costs not approved within the project budget', 'l_ar' => 'تكاليف الغير غير المعتمَدة ضمن موازنة المشروع', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Speaker, artist, performer or external consultant fees', 'l_ar' => 'أتعاب المتحدّثين أو الفنانين أو مقدّمي العروض أو الاستشاريين الخارجيين', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Additional services requested after approval of the Scope of Work', 'l_ar' => 'الخدمات الإضافية المطلوبة بعد اعتماد نطاق العمل', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Any new taxes, duties, levies or governmental fees imposed after execution of this Agreement', 'l_ar' => 'أي ضرائب أو رسوم أو عوائد أو رسوم حكومية جديدة تُفرض بعد توقيع هذه الاتفاقية', 't_en' => '', 't_ar' => ''],
                ],
            ],

            // ── 3 · PROJECT VALUE ────────────────────────────────────────────
            [
                'n' => '3', 'type' => 'prose',
                'en_title' => $isFixed ? 'Contract Value' : 'Estimated Project Value',
                'ar_title' => $isFixed ? 'قيمة العقد' : 'القيمة التقديرية للمشروع',
                'en' => $isFixed
                    ? [
                        "The Parties agree that the total value of the Project is {$money} ({$wordsEn}).",
                        'This amount is a fixed lump-sum contract value covering the Scope of Services set out in Article 1, and shall not vary save by a written amendment duly signed by both Parties.',
                        'Any addition to, or reduction of, the Scope of Services shall be agreed in writing and priced as a written variation before the relevant costs are committed.',
                    ]
                    : [
                        "The Parties agree that the current estimated value of the Project is {$money} ({$wordsEn}).",
                        'The above amount constitutes an estimated project budget and shall not be construed as a fixed lump-sum contract value. The final project cost may increase or decrease based on actual project requirements, final approved designs, Client instructions, supplier quotations, venue costs, accommodation requirements, logistical arrangements, governmental approvals and operational needs.',
                        'The Parties may amend the estimated budget through written approval without the need to execute a new Agreement.',
                    ],
                'ar' => $isFixed
                    ? [
                        "يتفق الطرفان على أن القيمة الإجمالية للمشروع هي {$moneyAr} ({$wordsAr}).",
                        'وهذا المبلغ قيمة عقد إجمالية ثابتة تغطّي نطاق الخدمات المبيّن في المادة (1)، ولا يجوز تغييره إلا بموجب تعديل خطّي موقّع حسب الأصول من الطرفين.',
                        'ويُتَّفق خطياً على أي إضافة إلى نطاق الخدمات أو انتقاص منه، ويُسعَّر ذلك بموجب تعديل خطّي قبل الالتزام بالتكاليف ذات الصلة.',
                    ]
                    : [
                        "يتفق الطرفان على أن القيمة التقديرية الحالية للمشروع هي {$moneyAr} ({$wordsAr}).",
                        'ويشكّل المبلغ أعلاه موازنةً تقديريةً للمشروع، ولا يجوز تفسيره على أنه قيمة عقد إجمالية ثابتة. وقد ترتفع التكلفة النهائية للمشروع أو تنخفض استناداً إلى متطلبات المشروع الفعلية، والتصاميم النهائية المعتمَدة، وتعليمات العميل، وعروض أسعار المورّدين، وتكاليف القاعات، ومتطلبات الإقامة، والترتيبات اللوجستية، والموافقات الحكومية، والاحتياجات التشغيلية.',
                        'ويجوز للطرفين تعديل الموازنة التقديرية بموجب موافقة خطّية دون الحاجة إلى إبرام اتفاقية جديدة.',
                    ],
            ],

            // ── 4 · FINANCIAL RESPONSIBILITY ─────────────────────────────────
            [
                'n' => '4', 'type' => 'prose',
                'en_title' => 'Financial Responsibility', 'ar_title' => 'المسؤولية المالية',
                'en' => [
                    'The Client shall be fully responsible for all project-related costs in accordance with the approved budgets, purchase orders, written approvals and project requirements.',
                    'Elite Business Hub shall represent the Client before hotels, venues, suppliers, service providers, governmental entities and other relevant stakeholders in relation to the management and execution of the Project.',
                ],
                'ar' => [
                    'يكون العميل مسؤولاً مسؤوليةً كاملةً عن جميع التكاليف المتعلقة بالمشروع وفقاً للموازنات المعتمَدة وأوامر الشراء والموافقات الخطّية ومتطلبات المشروع.',
                    'وتمثّل إيليت بزنس هَب العميلَ أمام الفنادق والقاعات والمورّدين ومقدّمي الخدمات والجهات الحكومية وسائر الجهات ذات العلاقة فيما يتعلّق بإدارة المشروع وتنفيذه.',
                ],
            ],

            // ── 4b · COST SHARING — only when the Client is more than one body ──
            count($sp) > 1 ? [
                'n' => '4b', 'type' => 'costshare',
                'en_title' => 'Cost Sharing Between the Client Entities', 'ar_title' => 'تقاسم التكاليف بين جهات العميل',
                'en' => ['Where the Client comprises more than one entity, the total cost of the Project shall be shared between those entities as set out below. Each entity is severally responsible for its stated share, and jointly responsible for the whole in the event of default by the other.'],
                'ar' => ['إذا تألّف العميل من أكثر من جهة، تُقتسَم التكلفة الإجمالية للمشروع بين تلك الجهات على النحو المبيّن أدناه. وتكون كل جهة مسؤولةً منفردةً عن حصّتها المحدّدة، ومسؤولةً بالتضامن عن كامل المبلغ في حال تخلّف الجهة الأخرى.'],
                'rows' => array_map(fn ($p) => [
                    'name_en' => $p['name_en'] ?? '', 'name_ar' => $p['name_ar'] ?? '', 'share' => $p['share'] ?? 0,
                ], $sp),
            ] : null,

            // ── 5 · PAYMENT TERMS ────────────────────────────────────────────
            [
                'n' => '5', 'type' => 'schedule',
                'en_title' => 'Payment Terms', 'ar_title' => 'شروط الدفع',
                'en' => [
                    'Payments shall be made by the Client to Elite Business Hub according to the following schedule:',
                    'All payments shall be calculated on the basis of the approved '.($isFixed ? 'contract value' : 'estimated budget').' and any subsequent written amendments approved by the Parties. Elite Business Hub shall commence and confirm bookings upon receipt of the first payment.',
                ],
                'ar' => [
                    'يقوم العميل بسداد الدفعات لصالح إيليت بزنس هَب وفقاً للجدول التالي:',
                    'وتُحتسَب جميع الدفعات على أساس '.($isFixed ? 'قيمة العقد المعتمَدة' : 'الموازنة التقديرية المعتمَدة').' وأي تعديلات خطّية لاحقة يوافق عليها الطرفان. وتبدأ إيليت بزنس هَب بإجراء الحجوزات وتأكيدها فور استلام الدفعة الأولى.',
                ],
                'schedule' => $f['payment_schedule'] ?? [],
            ],

            // ── 5b · ACCEPTANCE OF SERVICES ──────────────────────────────────
            // The clause that closes a project. Without a deemed-acceptance
            // deadline, a client who simply never replies leaves the work
            // formally unfinished and the final payment formally not due.
            [
                'n' => '5b', 'type' => 'prose',
                'en_title' => 'Acceptance of Services', 'ar_title' => 'قبول الخدمات',
                'en' => [
                    'Upon completion of the Event, Elite Business Hub shall submit to the Client a Certificate of Services Rendered listing the services delivered and the total amount due.',
                    "The Client shall sign the Certificate within {$accept} ({$accept}) business days of receipt, or within the same period provide a written, reasoned refusal identifying the specific services disputed.",
                    'If the Client neither signs the Certificate nor provides a written reasoned refusal within that period, the services described in it shall be deemed accepted in full on the day following its expiry, and the final payment shall fall due.',
                    'Acceptance of the Certificate does not waive any claim in respect of defects that could not reasonably have been discovered at the time of acceptance.',
                ],
                'ar' => [
                    'عند إتمام الفعالية، تقدّم إيليت بزنس هَب إلى العميل محضر إنجاز الخدمات مبيّناً فيه الخدمات المنفَّذة وإجمالي المبلغ المستحقّ.',
                    "ويلتزم العميل بتوقيع المحضر خلال {$acceptAr} ({$accept}) أيام عمل من تاريخ استلامه، أو أن يقدّم خلال المدة ذاتها رفضاً خطّياً مسبَّباً يحدّد فيه الخدمات محلّ الاعتراض.",
                    'وإذا لم يوقّع العميل المحضر ولم يقدّم رفضاً خطّياً مسبَّباً خلال تلك المدة، تُعَدّ الخدمات الواردة فيه مقبولةً بالكامل في اليوم التالي لانتهائها، وتُصبح الدفعة النهائية مستحقّة.',
                    'ولا يُسقط قبول المحضر أي مطالبة تتعلّق بعيوب لم يكن من الممكن اكتشافها بصورة معقولة وقت القبول.',
                ],
            ],

            // ── 6 · CANCELLATION AND REFUNDS ─────────────────────────────────
            [
                'n' => '6', 'type' => 'list',
                'en_title' => 'Cancellation and Refunds', 'ar_title' => 'الإلغاء والاسترداد',
                'en' => [
                    'In the event of cancellation of the Project, in whole or in part, the cancellation policies of the hotels, venues, suppliers and third-party service providers shall apply.',
                    'The Client shall remain liable for all costs committed, contracted, incurred or paid to third parties prior to the date of cancellation. By category, the following shall apply:',
                ],
                'ar' => [
                    'في حال إلغاء المشروع كلياً أو جزئياً، تُطبَّق سياسات الإلغاء الخاصة بالفنادق والقاعات والمورّدين ومقدّمي الخدمات من الغير.',
                    'ويبقى العميل مسؤولاً عن جميع التكاليف الملتزَم بها أو المتعاقَد عليها أو المتكبَّدة أو المدفوعة للغير قبل تاريخ الإلغاء. ويُطبَّق ما يلي بحسب الفئة:',
                ],
                'items' => [
                    ['l_en' => 'Accommodation and hotel food & beverage', 'l_ar' => 'الإقامة والمأكولات والمشروبات الفندقية',
                        't_en' => 'Subject to the cancellation policy of the respective hotel.',
                        't_ar' => 'تخضع لسياسة الإلغاء الخاصة بالفندق المعني.'],
                    ['l_en' => 'Equipment and rentals', 'l_ar' => 'المعدّات والتأجير',
                        't_en' => 'Free cancellation up to thirty (30) days before the Event; thereafter charges apply.',
                        't_ar' => 'الإلغاء مجاني حتى ثلاثين (30) يوماً قبل الفعالية؛ وبعد ذلك تُطبَّق الرسوم.'],
                    ['l_en' => 'Production and fabrication', 'l_ar' => 'الإنتاج والتصنيع',
                        't_en' => 'Once production has commenced it shall be charged in full; any item produced or delivered shall be charged in full.',
                        't_ar' => 'بمجرّد بدء الإنتاج يُحتسَب بالكامل؛ وأي عنصر تم إنتاجه أو تسليمه يُحتسَب بالكامل.'],
                    ['l_en' => 'Transportation', 'l_ar' => 'النقل',
                        't_en' => 'Free cancellation up to one (1) week before the Event.',
                        't_ar' => 'الإلغاء مجاني حتى أسبوع واحد (1) قبل الفعالية.'],
                    ['l_en' => 'Management fee', 'l_ar' => 'رسوم الإدارة',
                        't_en' => 'Fifty percent (50%) of the management fee shall be payable upon cancellation, in consideration of work performed to that date.',
                        't_ar' => 'يُستحَقّ عند الإلغاء خمسون بالمئة (50%) من رسوم الإدارة مقابل الأعمال المنجَزة حتى ذلك التاريخ.'],
                    ['l_en' => 'Services already delivered', 'l_ar' => 'الخدمات المنفَّذة فعلياً',
                        't_en' => 'Any service already delivered shall be charged in full.',
                        't_ar' => 'أي خدمة تم تنفيذها فعلياً تُحتسَب بالكامل.'],
                ],
            ],

            // ── 7 · TAXES AND GOVERNMENT FEES ────────────────────────────────
            [
                'n' => '7', 'type' => 'prose',
                'en_title' => 'Taxes and Government Fees', 'ar_title' => 'الضرائب والرسوم الحكومية',
                'en' => [
                    'All amounts payable under this Agreement shall be subject to the applicable taxes, duties, service charges, governmental fees and statutory levies imposed by the Hashemite Kingdom of Jordan or by any country in which any portion of the Project is implemented.',
                    sprintf(
                        'Unless otherwise agreed in writing, the rates applied shall be: %s%% sales tax and %s%% service charge on hotel-provided services; %s%% sales tax (VAT) on all other services provided by Elite Business Hub or by third-party vendors; and security clearance and government permits charged at %s%% of the total programme cost.',
                        self::pct($t['hotel_tax_pct'] ?? 8), self::pct($t['hotel_service_pct'] ?? 7),
                        self::pct($t['vat_pct'] ?? 16), self::pct($t['permits_pct'] ?? 10),
                    ),
                    'Should the Client hold a valid tax exemption, an official Tax Exemption Letter shall be provided to Elite Business Hub prior to final invoicing, and the applicable taxes shall be adjusted accordingly.',
                ],
                'ar' => [
                    'تخضع جميع المبالغ المستحقّة بموجب هذه الاتفاقية للضرائب والرسوم ورسوم الخدمة والرسوم الحكومية والعوائد القانونية المعمول بها في المملكة الأردنية الهاشمية أو في أي دولة يُنفَّذ فيها جزء من المشروع.',
                    sprintf(
                        'وما لم يُتَّفق خطياً على خلاف ذلك، تكون النسب المطبَّقة كما يلي: ضريبة مبيعات %s%% ورسم خدمة %s%% على الخدمات المقدَّمة من الفنادق؛ وضريبة مبيعات (القيمة المضافة) %s%% على سائر الخدمات المقدَّمة من إيليت بزنس هَب أو من مورّدي الغير؛ وتُحتسَب التصاريح الأمنية والحكومية بنسبة %s%% من إجمالي تكلفة البرنامج.',
                        self::pct($t['hotel_tax_pct'] ?? 8), self::pct($t['hotel_service_pct'] ?? 7),
                        self::pct($t['vat_pct'] ?? 16), self::pct($t['permits_pct'] ?? 10),
                    ),
                    'وإذا كان العميل يتمتّع بإعفاء ضريبي ساري المفعول، فيتعيّن تزويد إيليت بزنس هَب بكتاب إعفاء ضريبي رسمي قبل إصدار الفاتورة النهائية، وتُعدَّل الضرائب المطبَّقة تبعاً لذلك.',
                ],
            ],

            // ── 8 · ROLES AND RESPONSIBILITIES ───────────────────────────────
            [
                'n' => '8', 'type' => 'bullets',
                'en_title' => 'Roles and Responsibilities', 'ar_title' => 'الأدوار والمسؤوليات',
                // The lead-in to the list is the LAST paragraph on purpose: the
                // renderer prints prose, then bullets, so anything written after
                // this would surface above the list it belongs under.
                'en' => [
                    'Elite Business Hub shall act as the sole executive and supervisory authority for the Project, and shall be the sole entity authorised to issue operational instructions and directives throughout the implementation period of the Project.',
                    'The Client shall provide timely approvals, participant data and payments in accordance with this Agreement. Approvals shall not be unreasonably withheld or delayed, and any delay in approvals or payments that affects delivery shall not constitute a breach by Elite Business Hub.',
                    'The Contractor shall be responsible for planning, execution, management and coordination with:',
                ],
                'ar' => [
                    'تعمل إيليت بزنس هَب بصفتها الجهة التنفيذية والإشرافية الوحيدة للمشروع، وتكون الجهة الوحيدة المخوّلة بإصدار التعليمات والتوجيهات التشغيلية طوال فترة تنفيذ المشروع.',
                    'ويلتزم العميل بتقديم الموافقات وبيانات المشاركين والدفعات في مواعيدها وفقاً لهذه الاتفاقية. ولا يجوز حجب الموافقات أو تأخيرها دون سبب معقول، ولا يُعَدّ أي تأخير في الموافقات أو الدفعات يؤثّر في التنفيذ إخلالاً من جانب إيليت بزنس هَب.',
                    'ويكون المتعهّد مسؤولاً عن التخطيط والتنفيذ والإدارة والتنسيق مع:',
                ],
                'items' => [
                    ['l_en' => 'Hotels and venues', 'l_ar' => 'الفنادق والقاعات', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Suppliers and vendors', 'l_ar' => 'المورّدون والبائعون', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Subcontractors', 'l_ar' => 'المتعاقدون من الباطن', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Transportation providers', 'l_ar' => 'مزوّدو خدمات النقل', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Technical production companies', 'l_ar' => 'شركات الإنتاج الفني', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Governmental authorities', 'l_ar' => 'الجهات الحكومية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Security authorities', 'l_ar' => 'الجهات الأمنية', 't_en' => '', 't_ar' => ''],
                ],
            ],

            // ── 8b · CLIENT MATERIALS AND WARRANTIES ─────────────────────────
            // The Client hands over logos, speaker photographs, film and copy.
            // If it does not in fact hold those rights, the claim lands on
            // whoever printed and published them — so it warrants that it does.
            [
                'n' => '8b', 'type' => 'prose',
                'en_title' => 'Client Materials and Warranties', 'ar_title' => 'مواد العميل وإقراراته',
                'en' => [
                    'The Client warrants that all materials it provides to Elite Business Hub — including logos, trademarks, names, images, film, text and speaker materials — are either owned by the Client or licensed to it on terms permitting their use for the Event, and that their content complies with applicable law.',
                    'The Client shall indemnify Elite Business Hub against any claim, demand or penalty arising from the use of those materials as instructed, including claims by rights holders and orders of governmental or regulatory authorities.',
                    'Elite Business Hub may suspend, withdraw or remove any Client material where it receives a substantiated claim from a rights holder, an order from a competent authority, or where the Client is found to have provided inaccurate information about the material or its activities. It shall notify the Client without delay and, where practicable, before acting.',
                    'The Client shall provide participant data, speaker materials, approvals and branding assets by the deadlines set in the approved project plan. Elite Business Hub is not responsible for any consequence of late or incomplete delivery of Client materials.',
                ],
                'ar' => [
                    'يقرّ العميل ويضمن أن جميع المواد التي يزوّد بها إيليت بزنس هَب — بما في ذلك الشعارات والعلامات التجارية والأسماء والصور والأفلام والنصوص ومواد المتحدّثين — مملوكةٌ له أو مرخّصة له بشروط تسمح باستخدامها لأغراض الفعالية، وأن مضمونها متوافق مع القوانين المعمول بها.',
                    'ويلتزم العميل بتعويض إيليت بزنس هَب عن أي مطالبة أو دعوى أو غرامة تنشأ عن استخدام تلك المواد وفقاً لتعليماته، بما في ذلك مطالبات أصحاب الحقوق وأوامر الجهات الحكومية أو الرقابية.',
                    'ويجوز لإيليت بزنس هَب تعليق أي مادة من مواد العميل أو سحبها أو إزالتها إذا تلقّت مطالبةً مدعومة من صاحب حق، أو أمراً من جهة مختصّة، أو إذا تبيّن أن العميل قدّم معلومات غير صحيحة عن المادة أو عن نشاطه. وتُشعِر العميل بذلك دون تأخير، وقبل التصرّف متى أمكن.',
                    'ويلتزم العميل بتزويد بيانات المشاركين ومواد المتحدّثين والموافقات وأصول الهوية البصرية ضمن المواعيد المحدّدة في خطة المشروع المعتمَدة. ولا تتحمّل إيليت بزنس هَب مسؤولية أي أثر يترتّب على تأخّر تسليم مواد العميل أو نقصها.',
                ],
            ],

            // ── 9 · CONFIDENTIALITY ──────────────────────────────────────────
            [
                'n' => '9', 'type' => 'prose',
                'en_title' => 'Confidentiality', 'ar_title' => 'السرّية',
                'en' => ['The Parties shall maintain the confidentiality of all information, documentation, records and data exchanged in connection with this Agreement, and shall not disclose such information to any third party without the prior written consent of the other Party. This obligation shall survive the expiry or termination of this Agreement.'],
                'ar' => ['يلتزم الطرفان بالحفاظ على سرّية جميع المعلومات والوثائق والسجلات والبيانات المتبادلة فيما يتعلّق بهذه الاتفاقية، وبعدم إفشائها لأي طرف ثالث دون موافقة خطّية مسبقة من الطرف الآخر. ويظلّ هذا الالتزام سارياً بعد انقضاء هذه الاتفاقية أو إنهائها.'],
            ],

            // ── 10 · FORCE MAJEURE ───────────────────────────────────────────
            [
                'n' => '10', 'type' => 'prose',
                'en_title' => 'Force Majeure', 'ar_title' => 'القوّة القاهرة',
                'en' => [
                    'Neither Party shall be liable for any delay, interruption or failure to perform its obligations where such failure results from events beyond its reasonable control, including but not limited to natural disasters, war, civil unrest, governmental actions, epidemics, pandemics, security incidents or similar circumstances.',
                    'Costs already incurred or committed to third parties up to the date of the force-majeure event shall remain payable by the Client.',
                ],
                'ar' => [
                    'لا يُسأل أي من الطرفين عن أي تأخير أو انقطاع أو إخفاق في أداء التزاماته إذا نتج ذلك عن أحداث خارجة عن سيطرته المعقولة، بما في ذلك على سبيل المثال لا الحصر الكوارث الطبيعية والحروب والاضطرابات المدنية والإجراءات الحكومية والأوبئة والجوائح والحوادث الأمنية أو ما شابهها.',
                    'وتبقى التكاليف المتكبَّدة فعلاً أو الملتزَم بها تجاه الغير حتى تاريخ حدث القوّة القاهرة مستحقّةَ الدفع على العميل.',
                ],
            ],

            // ── 10b · TERM ───────────────────────────────────────────────────
            [
                'n' => '10b', 'type' => 'prose',
                'en_title' => 'Term of the Agreement', 'ar_title' => 'مدّة الاتفاقية',
                'en' => [
                    'This Agreement shall enter into force on the date of its signature by both Parties and shall remain effective until the Parties have fully performed their obligations under it, including delivery of the Event, acceptance of the services and settlement of all amounts due.',
                    'Expiry or termination of this Agreement shall not release either Party from liability for any breach committed during its term, nor from the obligations of confidentiality, intellectual property and payment, which survive it.',
                ],
                'ar' => [
                    'تدخل هذه الاتفاقية حيّز النفاذ من تاريخ توقيعها من الطرفين وتبقى سارية إلى حين تنفيذ الطرفين لكامل التزاماتهما بموجبها، بما في ذلك تنفيذ الفعالية وقبول الخدمات وتسوية جميع المبالغ المستحقّة.',
                    'ولا يُعفي انقضاء هذه الاتفاقية أو إنهاؤها أيّاً من الطرفين من المسؤولية عن أي إخلال وقع خلال مدّتها، ولا من التزامات السرّية والملكية الفكرية والسداد، التي تظلّ سارية بعدها.',
                ],
            ],

            // ── 11 · GOVERNING LAW ───────────────────────────────────────────
            [
                'n' => '11', 'type' => 'prose',
                'en_title' => 'Governing Law and Dispute Resolution', 'ar_title' => 'القانون الحاكم وتسوية النزاعات',
                'en' => [
                    'This Agreement shall be governed by and construed in accordance with the laws of the Hashemite Kingdom of Jordan. Where any part of the Project is implemented outside Jordan, each Party shall additionally comply with the mandatory laws of the country of implementation.',
                    'The Parties shall first attempt to resolve any dispute arising out of or in connection with this Agreement amicably, by negotiation between their authorised representatives, within thirty (30) days of written notice of the dispute.',
                    'Failing amicable settlement within that period, the competent courts of Amman, Jordan, shall have exclusive jurisdiction over the dispute.',
                ],
                'ar' => [
                    'تخضع هذه الاتفاقية لقوانين المملكة الأردنية الهاشمية وتُفسَّر وفقاً لها. وإذا نُفِّذ أي جزء من المشروع خارج الأردن، يلتزم كل طرف إضافةً إلى ذلك بالقواعد الآمرة في قوانين دولة التنفيذ.',
                    'ويسعى الطرفان أولاً إلى تسوية أي نزاع ينشأ عن هذه الاتفاقية أو يتّصل بها ودّياً بالتفاوض بين ممثّليهما المفوَّضين، خلال ثلاثين (30) يوماً من الإشعار الخطّي بالنزاع.',
                    'وإذا تعذّرت التسوية الودّية خلال تلك المدة، تختصّ محاكم عمّان المختصّة في المملكة الأردنية الهاشمية اختصاصاً حصرياً بالنظر في النزاع.',
                ],
            ],

            // ── 12 · AMENDMENTS ──────────────────────────────────────────────
            [
                'n' => '12', 'type' => 'prose',
                'en_title' => 'Amendments and Entire Agreement', 'ar_title' => 'التعديلات والاتفاق الكامل',
                'en' => [
                    'No provision of this Agreement may be amended, modified or waived except by a written amendment duly signed by both Parties.',
                    'This Agreement, together with its annexes, constitutes the entire agreement between the Parties and supersedes all prior discussions, proposals and understandings relating to the Project. The following form an integral part of it: the approved Scope of Work, the approved project plan and budget, and the Certificate of Services Rendered.',
                    'This Agreement is executed in two (2) counterparts of equal legal force, one for each Party.',
                ],
                'ar' => [
                    'لا يجوز تعديل أي حكم من أحكام هذه الاتفاقية أو تغييره أو التنازل عنه إلا بموجب تعديل خطّي موقّع حسب الأصول من الطرفين.',
                    'وتشكّل هذه الاتفاقية مع ملاحقها الاتفاقَ الكامل بين الطرفين، وتحلّ محلّ جميع المناقشات والعروض والتفاهمات السابقة المتعلقة بالمشروع. وتُعَدّ الوثائق التالية جزءاً لا يتجزّأ منها: نطاق العمل المعتمَد، وخطة المشروع والموازنة المعتمَدتان، ومحضر إنجاز الخدمات.',
                    'حُرِّرت هذه الاتفاقية من نسختين (2) متساويتين في القوة القانونية، لكل طرف نسخة.',
                ],
            ],
        ]));
    }

    /**
     * Articles 13–19: the standard conditions. Seeded into every new contract
     * and fully editable from there.
     *
     * @return list<array<string,mixed>>
     */
    public static function extraClauses(array $d): array
    {
        $t = $d['terms'] ?? [];
        $cure = (int) ($t['cure_days'] ?? 14);
        $cureAr = self::arabicNumeral($cure);

        $prevailing = ($d['meta']['prevailing_language'] ?? 'en') === 'ar' ? 'Arabic' : 'English';
        $prevailingAr = $prevailing === 'Arabic' ? 'العربي' : 'الإنجليزي';

        return [
            // ── 13 · INSURANCE AND LIABILITY ─────────────────────────────────
            [
                'type' => 'prose',
                'en_title' => 'Insurance and Liability', 'ar_title' => 'التأمين والمسؤولية',
                'en' => [
                    'Elite Business Hub shall exercise reasonable professional care, skill and diligence in the performance of its obligations, and shall engage qualified and duly licensed suppliers and service providers where required.',
                    'Neither Party shall be liable to the other for indirect or consequential loss, including loss of profit or reputation. The aggregate liability of Elite Business Hub under this Agreement shall not exceed the total amounts actually paid to it by the Client under this Agreement.',
                    'The Client shall be responsible for the conduct of its own guests, delegates and invitees, and for any damage caused by them to the venue or to third-party property.',
                ],
                'ar' => [
                    'تبذل إيليت بزنس هَب العناية والمهارة والحرص المهني المعقول في أداء التزاماتها، وتستعين بمورّدين ومقدّمي خدمات مؤهّلين ومرخّصين حسب الأصول عند الاقتضاء.',
                    'ولا يُسأل أي من الطرفين تجاه الآخر عن الأضرار غير المباشرة أو التبعية، بما في ذلك فوات الربح أو الإضرار بالسمعة. ولا تتجاوز المسؤولية الإجمالية لإيليت بزنس هَب بموجب هذه الاتفاقية إجمالي المبالغ المدفوعة لها فعلياً من العميل بموجبها.',
                    'ويكون العميل مسؤولاً عن تصرّفات ضيوفه والمشاركين والمدعوّين من قِبَله، وعن أي ضرر يُلحقونه بالقاعة أو بممتلكات الغير.',
                ],
            ],

            // ── 14 · INTELLECTUAL PROPERTY ───────────────────────────────────
            [
                'type' => 'prose',
                'en_title' => 'Intellectual Property and Media Rights', 'ar_title' => 'الملكية الفكرية والحقوق الإعلامية',
                'en' => [
                    'Ownership of all designs, concepts, creative materials, reports and project deliverables specifically created for the Project shall transfer to the Client upon full settlement of all amounts due under this Agreement.',
                    'Each Party retains ownership of its own trademarks, logos and pre-existing materials, and grants the other a limited licence to use those marks solely for the purpose of promoting and delivering the Event.',
                    'Elite Business Hub may photograph and film the Event and use such material for its portfolio and marketing, unless the Client notifies it otherwise in writing before the Event.',
                ],
                'ar' => [
                    'تنتقل ملكية جميع التصاميم والأفكار والمواد الإبداعية والتقارير ومخرجات المشروع التي أُنشئت خصيصاً للمشروع إلى العميل عند السداد الكامل لجميع المبالغ المستحقّة بموجب هذه الاتفاقية.',
                    'ويحتفظ كل طرف بملكية علاماته التجارية وشعاراته وموادّه السابقة، ويمنح الطرف الآخر ترخيصاً محدوداً باستخدام تلك العلامات لغرض الترويج للفعالية وتنفيذها حصراً.',
                    'ويجوز لإيليت بزنس هَب تصوير الفعالية فوتوغرافياً وفيديوياً واستخدام تلك المواد في أعمالها التعريفية والتسويقية، ما لم يُشعِرها العميل خطياً بخلاف ذلك قبل الفعالية.',
                ],
            ],

            // ── 15 · HEALTH, SAFETY AND SECURITY ─────────────────────────────
            [
                'type' => 'prose',
                'en_title' => 'Health, Safety and Security', 'ar_title' => 'الصحة والسلامة والأمن',
                'en' => [
                    'Elite Business Hub shall execute the Project in accordance with all applicable health, safety and security requirements imposed by the competent authorities and by the event venues, and shall obtain the security clearances and government permits required for the Event.',
                    'The Client shall provide the participant data required for security clearance sufficiently in advance. Any delay in providing such data that results in refused or late clearance shall not constitute a breach by Elite Business Hub.',
                ],
                'ar' => [
                    'تنفّذ إيليت بزنس هَب المشروع وفقاً لجميع متطلبات الصحة والسلامة والأمن المعمول بها والمفروضة من الجهات المختصّة ومن مواقع إقامة الفعالية، وتتولّى استصدار التصاريح الأمنية والحكومية اللازمة للفعالية.',
                    'ويلتزم العميل بتزويد بيانات المشاركين اللازمة للتصاريح الأمنية في وقت كافٍ مسبقاً. ولا يُعَدّ أي تأخير في تقديم تلك البيانات يؤدّي إلى رفض التصريح أو تأخّره إخلالاً من جانب إيليت بزنس هَب.',
                ],
            ],

            // ── 16 · TERMINATION ─────────────────────────────────────────────
            [
                'type' => 'prose',
                'en_title' => 'Termination', 'ar_title' => 'إنهاء الاتفاقية',
                'en' => [
                    "Either Party may terminate this Agreement if the other Party commits a material breach and fails to remedy such breach within {$cure} ({$cure}) days after receipt of a written notice requiring remedy.",
                    'Either Party may also withdraw from this Agreement without cause by written notice, provided that the withdrawing Party compensates the other for all services actually rendered, all costs committed to third parties, and any loss directly caused by the withdrawal.',
                    'On termination for any reason, the Client shall pay for all services delivered up to the date of termination, together with all costs committed to third parties which cannot be recovered, and the cancellation charges set out in the Cancellation and Refunds article.',
                ],
                'ar' => [
                    "يجوز لأيٍّ من الطرفين إنهاء هذه الاتفاقية إذا ارتكب الطرف الآخر إخلالاً جوهرياً ولم يعالجه خلال {$cureAr} ({$cure}) يوماً من تاريخ استلامه إشعاراً خطّياً يطالبه بالمعالجة.",
                    'كما يجوز لأيٍّ من الطرفين العدول عن هذه الاتفاقية دون سبب بموجب إشعار خطّي، شريطة أن يعوّض الطرفَ الآخر عن جميع الخدمات المنفَّذة فعلياً، وكافة التكاليف الملتزَم بها تجاه الغير، وأي ضرر ناجم مباشرةً عن العدول.',
                    'وعند الإنهاء لأي سبب، يلتزم العميل بسداد قيمة جميع الخدمات المنفَّذة حتى تاريخ الإنهاء، إضافةً إلى كافة التكاليف الملتزَم بها تجاه الغير وغير القابلة للاسترداد، ورسوم الإلغاء المنصوص عليها في مادة الإلغاء والاسترداد.',
                ],
            ],

            // ── 17 · SUBCONTRACTING ──────────────────────────────────────────
            [
                'type' => 'prose',
                'en_title' => 'Assignment and Subcontracting', 'ar_title' => 'التنازل والتعاقد من الباطن',
                'en' => [
                    'Elite Business Hub may engage subcontractors, suppliers and specialised service providers to perform portions of the Project, provided that Elite Business Hub shall remain responsible to the Client for the quality and performance of such services.',
                    'Neither Party may assign this Agreement, in whole or in part, without the prior written consent of the other Party.',
                ],
                'ar' => [
                    'يجوز لإيليت بزنس هَب الاستعانة بمتعاقدين من الباطن ومورّدين ومقدّمي خدمات متخصّصين لتنفيذ أجزاء من المشروع، على أن تبقى إيليت بزنس هَب مسؤولةً تجاه العميل عن جودة تلك الخدمات وحُسن أدائها.',
                    'ولا يجوز لأيٍّ من الطرفين التنازل عن هذه الاتفاقية كلياً أو جزئياً دون موافقة خطّية مسبقة من الطرف الآخر.',
                ],
            ],

            // ── 18 · NOTICES ─────────────────────────────────────────────────
            [
                'type' => 'prose',
                'en_title' => 'Notices', 'ar_title' => 'الإشعارات',
                'en' => ['Any notice, approval, request, instruction or communication under this Agreement shall be made in writing and delivered to the officially designated addresses and representatives of the Parties, by hand, by courier or by email. Notice by email shall be deemed received on the next business day.'],
                'ar' => ['يكون أي إشعار أو موافقة أو طلب أو تعليمات أو مراسلة بموجب هذه الاتفاقية خطّياً، ويُسلَّم إلى العناوين والممثّلين المعتمَدين رسمياً للطرفين، باليد أو بالبريد السريع أو بالبريد الإلكتروني. ويُعَدّ الإشعار بالبريد الإلكتروني مستلَماً في يوم العمل التالي.'],
            ],

            // ── 19 · GOVERNING LANGUAGE ──────────────────────────────────────
            [
                'type' => 'prose',
                'en_title' => 'Governing Language', 'ar_title' => 'لغة الاتفاقية',
                'en' => ["This Agreement is executed in the English and Arabic languages. In the event of any discrepancy between the two texts, the {$prevailing} version shall constitute the official and controlling version for all purposes of interpretation and enforcement."],
                'ar' => ["حُرِّرت هذه الاتفاقية باللغتين الإنجليزية والعربية. وفي حال وجود أي تعارض بين النصّين، يكون النصّ {$prevailingAr} هو النصّ الرسمي والمعتمَد لجميع أغراض التفسير والتنفيذ."],
            ],
        ];
    }

    /**
     * The standard contract body as editable blocks. Variables are interpolated
     * once, here — from then on the contract owns plain, editable text.
     *
     * @return list<array{id:string,type:string,title_en:string,title_ar:string,en:list<string>,ar:list<string>,items:array,rows:array}>
     */
    public static function blocks(array $d): array
    {
        $out = [];
        $n = 0;

        foreach ([...self::clauses($d), ...self::extraClauses($d)] as $c) {
            $n++;
            $out[] = [
                'id' => 'b'.$n,
                'type' => $c['type'] ?? 'prose',
                'title_en' => $c['en_title'] ?? '',
                'title_ar' => $c['ar_title'] ?? '',
                // The renderer prints prose, then bullets. A clause with a list
                // must therefore end its prose on the lead-in line — there is no
                // "text after the list" slot, and inventing one would mean a
                // schema change across the editor, the preview and the PDF.
                'en' => array_values($c['en'] ?? []),
                'ar' => array_values($c['ar'] ?? []),
                'items' => array_values($c['items'] ?? []),
                'rows' => array_values($c['rows'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * "JOD 350,000.00 (Three Hundred Fifty Thousand Jordanian Dinars Only)".
     *
     * A figure alone can be altered with a pen; the words beside it are how a
     * contract makes the amount unambiguous, and every serious agreement the
     * company issues carries both.
     *
     * @return array{0:string,1:string}
     */
    public static function inWords(int $cents, string $currency): array
    {
        $whole = intdiv($cents, 100);
        $name = Event::CURRENCIES[$currency][1] ?? $currency;

        $en = ucwords(Number::spell($whole)).' '.($whole === 1 ? $name : $name.'s').' Only';
        $ar = Number::spell($whole, 'ar').' '.self::arabicCurrency($currency).' لا غير';

        return [$en, $ar];
    }

    /** 16 not 16.0, 7.5 not 7.50 — percentages read as they are spoken. */
    private static function pct(float|int|string $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    /**
     * Wrap a run in FIRST STRONG ISOLATE … POP DIRECTIONAL ISOLATE, so a Latin
     * name or date embedded in Arabic keeps its own direction and stays in one
     * piece. Invisible characters, no markup — they survive into the PDF, which
     * a <span dir="ltr"> would not once the text is stored as a plain string.
     */
    private static function isolate(string $s, bool $nowrap = false): string
    {
        if ($s === '') {
            return '';
        }

        // Isolation fixes the order; only a non-breaking space stops the run
        // being split at a line end. Long runs — an event name, a venue — keep
        // ordinary spaces so they can still wrap.
        return "\u{2068}".($nowrap ? str_replace(' ', "\u{00A0}", $s) : $s)."\u{2069}";
    }

    /** Arabic-Indic digits, for numbers set inside Arabic legal text. */
    private static function arabicNumeral(int $n): string
    {
        return strtr((string) $n, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }

    private static function arabicCurrency(string $code): string
    {
        return match ($code) {
            'JOD' => 'ديناراً أردنياً',
            'USD' => 'دولاراً أمريكياً',
            default => $code,
        };
    }
}
