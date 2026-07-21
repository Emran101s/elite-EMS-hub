<?php

namespace App\Support;

/**
 * Standard bilingual (EN/AR) clause text for the Event Management Services Agreement.
 * Variables (parties, shares, budget assumptions, event) are interpolated from the
 * contract's stored data so the legal wording stays consistent across events.
 */
class ContractClauses
{
    /** Recitals / parties block shown before the numbered clauses. */
    public static function recitals(array $d): array
    {
        $sp = collect($d['second_parties'] ?? [])->pluck('name_en')->filter()->join(' and ');
        $spAr = collect($d['second_parties'] ?? [])->pluck('name_ar')->filter()->join(' و');
        $ev = $d['event'] ?? [];

        return [
            'en' => [
                "This Event Management Services Agreement (the “Agreement”) is made in {$d['meta']['place']} on {$d['meta']['date']} by and between:",
                "First Party: {$d['first_party']['name_en']}, represented by {$d['first_party']['rep_en']} (hereinafter “Elite Business Hub” or the “Management Company”); and",
                "Second Party: {$sp} (jointly and severally, the “Client”).",
                "Whereas the Client wishes to organise “{$ev['name']}” ({$ev['dates']}, {$ev['venue']}, {$ev['location']}), and Elite Business Hub agrees to plan, manage and deliver the said event on a full-service basis; the parties have agreed as follows:",
            ],
            'ar' => [
                "أُبرمت اتفاقية خدمات إدارة الفعاليات هذه (\"الاتفاقية\") في {$d['meta']['place']} بتاريخ {$d['meta']['date']} بين كلٍّ من:",
                "الطرف الأول: {$d['first_party']['name_ar']}، ويمثّلها {$d['first_party']['rep_ar']} (ويُشار إليها فيما بعد بـ \"إيليت بزنس هَب\" أو \"الشركة المنظِّمة\")؛",
                "الطرف الثاني: {$spAr} (ويُشار إليهم مجتمعين ومتضامنين بـ \"العميل\").",
                "وحيث إن العميل يرغب في تنظيم فعالية \"{$ev['name']}\" ({$ev['dates']}، {$ev['venue']}، {$ev['location']})، وحيث توافق إيليت بزنس هَب على تخطيط الفعالية المذكورة وإدارتها وتنفيذها بنظام الخدمة الشاملة؛ فقد اتفق الطرفان على ما يلي:",
            ],
        ];
    }

    /** Ordered numbered clauses. Each: n, en_title, ar_title, type, plus content. */
    public static function clauses(array $d): array
    {
        $a = $d['assumptions'] ?? [];
        $f = $d['financials'] ?? [];
        $sp = $d['second_parties'] ?? [];
        $sp1 = $sp[0] ?? ['name_en' => 'the first funding entity', 'name_ar' => 'الجهة المموّلة الأولى', 'share' => 80];
        $sp2 = $sp[1] ?? ['name_en' => 'the second funding entity', 'name_ar' => 'الجهة المموّلة الثانية', 'share' => 20];
        $cur = $f['currency'] ?? 'JOD';
        $fee = rtrim(rtrim(number_format((float) ($f['management_fee_pct'] ?? 15), 1), '0'), '.');

        return [
            // 1 · SCOPE OF WORK
            [
                'n' => '1', 'type' => 'prose',
                'en_title' => 'Scope of Work', 'ar_title' => 'نطاق العمل',
                'en' => [
                    'Elite Business Hub shall act as the sole event management company and the single point of coordination for the Event, and shall plan, manage, supervise and deliver the Event from end to end. The scope of services includes, without limitation:',
                    'overall project management and full on-site supervision; concept, creative direction and design of all event materials; production and technical delivery (stage, audio-visual, lighting, sound, LED screens and livestreaming); exhibition design, build-up and management; venue sourcing, contracting and management; equipment, furniture and fit-out; accommodation and hospitality; catering coordination; transportation and logistics; registration and participant management; interpretation and translation; branding, signage and printed collateral; and security clearance and government permits.',
                    'All services and third-party suppliers shall be sourced, contracted, managed and supervised by Elite Business Hub on the Client’s behalf, and Elite Business Hub shall coordinate with all hotels, vendors, suppliers and competent authorities in respect of the Event.',
                ],
                'ar' => [
                    'تعمل إيليت بزنس هَب بصفتها الشركة المنظِّمة الوحيدة للفعالية ونقطة التنسيق الموحّدة، وتتولّى تخطيط الفعالية وإدارتها والإشراف عليها وتنفيذها من البداية إلى النهاية. ويشمل نطاق الخدمات، على سبيل المثال لا الحصر:',
                    'الإدارة الشاملة للمشروع والإشراف الميداني الكامل؛ وابتكار الفكرة والتوجيه الإبداعي وتصميم كافة مواد الفعالية؛ والإنتاج والتنفيذ الفني (المسرح، والوسائط السمعية والبصرية، والإضاءة، والصوت، وشاشات الـ LED، والبث المباشر)؛ وتصميم المعرض وتجهيزه وإدارته؛ وتأمين القاعات والتعاقد عليها وإدارتها؛ والمعدّات والأثاث والتجهيزات؛ والإقامة والضيافة؛ وتنسيق التموين؛ والنقل والخدمات اللوجستية؛ والتسجيل وإدارة المشاركين؛ والترجمة الفورية والتحريرية؛ والهوية البصرية واللافتات والمطبوعات؛ والتصاريح الأمنية والحكومية.',
                    'تتولّى إيليت بزنس هَب تأمين جميع الخدمات ومورّدي الغير والتعاقد معهم وإدارتهم والإشراف عليهم نيابةً عن العميل، كما تتولّى التنسيق مع كافة الفنادق والمورّدين والجهات المختصّة فيما يخصّ الفعالية.',
                ],
            ],

            // 2 · PROJECT PLAN & BUDGET
            [
                'n' => '2', 'type' => 'prose',
                'en_title' => 'Project Plan & Budget', 'ar_title' => 'خطة المشروع والموازنة',
                'en' => [
                    'The Event shall be executed in accordance with the project plan and budget prepared by Elite Business Hub and tracked through its project-management system.',
                    'The budget is an estimate prepared in good faith and is not a fixed figure; the final amount may increase or decrease according to the final headcount, supplier availability and confirmed specifications. Elite Business Hub shall obtain the Client’s approval for the budget, and for any material change to it, before committing the relevant costs.',
                    "The current budget is based on the following assumptions: approximately {$a['attendees_min']}–{$a['attendees_max']} attendees; {$a['catering_en']}; and {$a['rooms']} rooms for {$a['nights']} nights per guest.",
                ],
                'ar' => [
                    'تُنفَّذ الفعالية وفقاً لخطة المشروع والموازنة المُعدّتين من قِبَل إيليت بزنس هَب، ويجري تتبّعهما من خلال نظام إدارة المشاريع الخاص بها.',
                    'الموازنة تقديريّة أُعِدّت بحسن نيّة وهي غير ثابتة؛ وقد يزيد المبلغ النهائي أو ينقص تبعاً للعدد النهائي للحضور، وتوافر المورّدين، والمواصفات المعتمدة. وتلتزم إيليت بزنس هَب بالحصول على موافقة العميل على الموازنة وعلى أي تغيير جوهري فيها قبل الالتزام بالتكاليف ذات الصلة.',
                    "وتستند الموازنة الحالية إلى الافتراضات التالية: نحو {$a['attendees_min']}–{$a['attendees_max']} مشاركاً؛ و{$a['catering_ar']}؛ و{$a['rooms']} غرفة لمدّة {$a['nights']} ليالٍ لكل ضيف.",
                ],
            ],

            // 3 · COST SHARING
            [
                'n' => '3', 'type' => 'costshare',
                'en_title' => 'Financial Responsibility (Cost Sharing)', 'ar_title' => 'المسؤولية المالية (تقاسم التكاليف)',
                'en' => ['The total cost of the Event shall be shared between the entities comprising the Client as set out below. Each entity is responsible for its stated share of the total cost.'],
                'ar' => ['يُقتسَم إجمالي تكلفة الفعالية بين الجهات المكوِّنة للعميل على النحو المبيّن أدناه. وتكون كل جهة مسؤولةً عن حصّتها المحدّدة من إجمالي التكلفة.'],
                'rows' => [
                    ['name_en' => $sp1['name_en'], 'name_ar' => $sp1['name_ar'], 'share' => $sp1['share'] ?? 80],
                    ['name_en' => $sp2['name_en'], 'name_ar' => $sp2['name_ar'], 'share' => $sp2['share'] ?? 20],
                ],
            ],

            // 4 · PAYMENT TERMS
            [
                'n' => '4', 'type' => 'schedule',
                'en_title' => 'Payment Terms', 'ar_title' => 'شروط الدفع',
                'en' => ['Payments shall be made by the Client to Elite Business Hub against the estimated total cost according to the schedule below. Elite Business Hub shall commence and confirm bookings upon receipt of the signing payment.'],
                'ar' => ['يقوم العميل بسداد الدفعات لصالح إيليت بزنس هَب مقابل التكلفة الإجمالية التقديريّة وفقاً للجدول أدناه. وتبدأ إيليت بزنس هَب بإجراء الحجوزات وتأكيدها فور استلام دفعة التوقيع.'],
                'schedule' => $f['payment_schedule'] ?? [],
            ],

            // 5 · CANCELLATION
            [
                'n' => '5', 'type' => 'list',
                'en_title' => 'Cancellation & Refund Policy', 'ar_title' => 'سياسة الإلغاء والاسترداد',
                'en' => ['In the event of cancellation, the following shall apply per service category:'],
                'ar' => ['في حال الإلغاء، يُطبَّق ما يلي بحسب فئة الخدمة:'],
                'items' => [
                    ['l_en' => 'Accommodation & hotel F&B', 'l_ar' => 'الإقامة والمأكولات والمشروبات في الفندق',
                        't_en' => 'Subject to the cancellation policy of the respective hotel.',
                        't_ar' => 'تخضع لسياسة الإلغاء الخاصة بالفندق المعني.'],
                    ['l_en' => 'Equipment', 'l_ar' => 'المعدّات',
                        't_en' => 'Free cancellation up to thirty (30) days before the Event date; thereafter, charges apply.',
                        't_ar' => 'الإلغاء مجاني حتى ثلاثين (30) يوماً قبل تاريخ الفعالية؛ وبعد ذلك تُطبَّق الرسوم.'],
                    ['l_en' => 'Production', 'l_ar' => 'الإنتاج',
                        't_en' => 'Once production has commenced it shall be charged in full; any item produced or delivered shall be charged in full.',
                        't_ar' => 'بمجرّد بدء الإنتاج يُحتسَب بالكامل؛ وأي عنصر تم إنتاجه أو تسليمه يُحتسَب بالكامل.'],
                    ['l_en' => 'Management fee', 'l_ar' => 'رسوم الإدارة',
                        't_en' => 'Fifty percent (50%) of the expected management fee shall be payable upon cancellation.',
                        't_ar' => 'يُستحَقّ عند الإلغاء خمسون بالمئة (50%) من رسوم الإدارة المتوقّعة.'],
                    ['l_en' => 'Transportation', 'l_ar' => 'النقل',
                        't_en' => 'Free cancellation up to one (1) week before the Event.',
                        't_ar' => 'الإلغاء مجاني حتى أسبوع واحد (1) قبل الفعالية.'],
                    ['l_en' => 'General', 'l_ar' => 'أحكام عامة',
                        't_en' => 'Any service already delivered shall be charged in full.',
                        't_ar' => 'أي خدمة تم تسليمها فعلياً تُحتسَب بالكامل.'],
                ],
            ],

            // 6 · TAXES & FEES
            [
                'n' => '6', 'type' => 'prose',
                'en_title' => 'Taxes & Fees', 'ar_title' => 'الضرائب والرسوم',
                'en' => [
                    "All amounts are quoted in {$cur} and are subject to applicable taxes and fees: hotel-provided services (accommodation & meeting package) are subject to 8% Sales Tax and 7% Service Charge; all other services provided by Elite Business Hub or third-party vendors are subject to 16% Sales Tax (VAT); and security clearance & government permits are charged at 10% of the total programme cost, secured via a specialised external agent.",
                    'Should the Client hold a valid tax-exemption in Jordan, an official Tax Exemption Letter shall be provided to Elite Business Hub prior to final invoicing, and applicable taxes shall be adjusted accordingly.',
                ],
                'ar' => [
                    "جميع المبالغ مُقدَّرة بعملة {$cur} وتخضع للضرائب والرسوم المعمول بها: الخدمات المقدَّمة من الفندق (الإقامة وباقة الاجتماعات) تخضع لضريبة مبيعات 8% ورسم خدمة 7%؛ وسائر الخدمات المقدَّمة من إيليت بزنس هَب أو من مورّدي الغير تخضع لضريبة مبيعات (القيمة المضافة) بنسبة 16%؛ وتُحتسَب التصاريح الأمنية والحكومية بنسبة 10% من إجمالي تكلفة البرنامج، وتُؤمَّن عبر وكيل خارجي متخصّص.",
                    'وإذا كان العميل يتمتّع بإعفاء ضريبي ساري المفعول في الأردن، فيتعيّن تزويد إيليت بزنس هَب بكتاب إعفاء ضريبي رسمي قبل إصدار الفاتورة النهائية، وتُعدَّل الضرائب المطبَّقة تبعاً لذلك.',
                ],
            ],

            // 7 · RESPONSIBILITIES
            [
                'n' => '7', 'type' => 'prose',
                'en_title' => 'Roles & Responsibilities', 'ar_title' => 'الأدوار والمسؤوليات',
                'en' => [
                    'Elite Business Hub shall act as the sole coordinator between the Client and all hotels, vendors, suppliers and authorities, and shall supervise all production, exhibition, venue, equipment and logistics elements of the Event.',
                    'The Client shall provide timely approvals, participant data and its share of the payments in accordance with this Agreement. Approvals shall not be unreasonably withheld or delayed, and any delay in approvals or payments that affects delivery shall not constitute a breach by Elite Business Hub.',
                ],
                'ar' => [
                    'تعمل إيليت بزنس هَب بصفتها المنسّق الوحيد بين العميل وجميع الفنادق والمورّدين والجهات المختصّة، وتُشرف على جميع عناصر الإنتاج والمعرض والقاعة والمعدّات والخدمات اللوجستية للفعالية.',
                    'يلتزم العميل بتقديم الموافقات وبيانات المشاركين وحصّته من الدفعات في مواعيدها وفقاً لهذه الاتفاقية. ولا يجوز حجب الموافقات أو تأخيرها دون سبب معقول، ولا يُعَدّ أي تأخير في الموافقات أو الدفعات يؤثّر في التنفيذ إخلالاً من جانب إيليت بزنس هَب.',
                ],
            ],

            // 8 · CONFIDENTIALITY
            [
                'n' => '8', 'type' => 'prose',
                'en_title' => 'Confidentiality', 'ar_title' => 'السرّية',
                'en' => ['Each party shall keep confidential all non-public information disclosed by the other party in connection with the Event and this Agreement, and shall use it solely for the purpose of delivering the Event.'],
                'ar' => ['يلتزم كل طرف بالحفاظ على سرّية جميع المعلومات غير العلنية التي يفصح عنها الطرف الآخر فيما يتعلّق بالفعالية وهذه الاتفاقية، وباستخدامها حصراً لغرض تنفيذ الفعالية.'],
            ],

            // 9 · FORCE MAJEURE
            [
                'n' => '9', 'type' => 'prose',
                'en_title' => 'Force Majeure', 'ar_title' => 'القوّة القاهرة',
                'en' => ['Neither party shall be liable for any failure or delay caused by events beyond its reasonable control, including acts of God, government action, or emergencies. Costs already incurred or committed to third parties up to the force-majeure event remain payable by the Client.'],
                'ar' => ['لا يُسأل أي من الطرفين عن أي إخفاق أو تأخير ناجم عن ظروف خارجة عن سيطرته المعقولة، بما في ذلك القضاء والقدر، أو إجراءات الحكومة، أو حالات الطوارئ. وتبقى التكاليف المتكبَّدة فعلاً أو الملتزَم بها تجاه الغير حتى وقوع حدث القوّة القاهرة مستحقّةَ الدفع على العميل.'],
            ],

            // 10 · GOVERNING LAW
            [
                'n' => '10', 'type' => 'prose',
                'en_title' => 'Governing Law & Jurisdiction', 'ar_title' => 'القانون الحاكم والاختصاص القضائي',
                'en' => ['This Agreement shall be governed by and construed in accordance with the laws of the Hashemite Kingdom of Jordan, and the competent courts of Amman shall have jurisdiction over any dispute arising from it.'],
                'ar' => ['تخضع هذه الاتفاقية لقوانين المملكة الأردنية الهاشمية وتُفسَّر وفقاً لها، وتختصّ محاكم عمّان المختصّة بالنظر في أي نزاع ينشأ عنها.'],
            ],

            // 11 · ENTIRE AGREEMENT
            [
                'n' => '11', 'type' => 'prose',
                'en_title' => 'Amendments & Entire Agreement', 'ar_title' => 'التعديلات والاتفاقية الكاملة',
                'en' => ['This Agreement, together with the approved project plan and budget, constitutes the entire agreement between the parties. Any amendment must be made in writing and signed by both parties. Where any conflict arises between the English and Arabic texts, the parties shall interpret them together in good faith to give effect to their common intention.'],
                'ar' => ['تشكّل هذه الاتفاقية، مع خطة المشروع والموازنة المعتمَدتين، الاتفاقَ الكامل بين الطرفين. ويجب أن يتمّ أي تعديل كتابةً وبتوقيع الطرفين. وفي حال وجود أي تعارض بين النصّين الإنجليزي والعربي، يفسّرهما الطرفان معاً بحسن نيّة بما يحقّق قصدهما المشترك.'],
            ],
        ];
    }
}
