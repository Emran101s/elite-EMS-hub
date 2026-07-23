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

        // The contract stands on its own agreed figure, not on a live budget estimate.
        $valueCents = (int) ($f['contract_value_cents'] ?? $f['estimated_total_cents'] ?? 0);
        $money = $cur.' '.number_format($valueCents / 100, 2);
        $isFixed = ($f['value_mode'] ?? 'fixed') === 'fixed';

        return [
            // 1 · SCOPE OF WORK
            [
                'n' => '1', 'type' => 'bullets',
                'en_title' => 'Scope of Work', 'ar_title' => 'نطاق العمل',
                'en' => [
                    'Elite Business Hub shall act as the sole event management company and the single point of coordination for the Event, and shall plan, manage, supervise and deliver the Event from end to end. The scope of services comprises:',
                ],
                'ar' => [
                    'تعمل إيليت بزنس هَب بصفتها الشركة المنظِّمة الوحيدة للفعالية ونقطة التنسيق الموحّدة، وتتولّى تخطيط الفعالية وإدارتها والإشراف عليها وتنفيذها من البداية إلى النهاية. ويشمل نطاق الخدمات ما يلي:',
                ],
                // Edit, add or remove these freely — this is the deliverables list.
                'items' => [
                    ['l_en' => 'Project management & on-site supervision', 'l_ar' => 'إدارة المشروع والإشراف الميداني',
                        't_en' => 'End-to-end coordination and full on-site delivery.', 't_ar' => 'التنسيق الشامل والتنفيذ الميداني الكامل.'],
                    ['l_en' => 'Concept & creative direction', 'l_ar' => 'الفكرة والتوجيه الإبداعي',
                        't_en' => 'Event concept, design and all event materials.', 't_ar' => 'ابتكار فكرة الفعالية وتصميمها وكافة موادها.'],
                    ['l_en' => 'Production & technical delivery', 'l_ar' => 'الإنتاج والتنفيذ الفني',
                        't_en' => 'Stage, audio-visual, lighting, sound, LED screens and livestreaming.', 't_ar' => 'المسرح والوسائط السمعية والبصرية والإضاءة والصوت وشاشات الـ LED والبث المباشر.'],
                    ['l_en' => 'Venue & hotel liaison', 'l_ar' => 'التنسيق مع القاعات والفنادق',
                        't_en' => 'Sourcing, contracting and day-to-day coordination.', 't_ar' => 'التأمين والتعاقد والتنسيق اليومي.'],
                    ['l_en' => 'Branding & printing', 'l_ar' => 'الهوية البصرية والمطبوعات',
                        't_en' => 'Signage, boards, stands and printed collateral.', 't_ar' => 'اللافتات واللوحات والحوامل والمطبوعات.'],
                    ['l_en' => 'Accommodation & hospitality', 'l_ar' => 'الإقامة والضيافة',
                        't_en' => 'Room blocks, rooming lists and guest services.', 't_ar' => 'حجوزات الغرف وقوائم الإقامة وخدمات الضيوف.'],
                    ['l_en' => 'Catering coordination', 'l_ar' => 'تنسيق التموين',
                        't_en' => 'Menus, service timings and dietary requirements.', 't_ar' => 'قوائم الطعام وأوقات الخدمة والمتطلّبات الغذائية.'],
                    ['l_en' => 'Ground transportation', 'l_ar' => 'النقل البرّي',
                        't_en' => 'Airport and event transfers, and on-site logistics.', 't_ar' => 'التنقّلات من وإلى المطار والفعالية والخدمات اللوجستية الميدانية.'],
                    ['l_en' => 'Registration & participant management', 'l_ar' => 'التسجيل وإدارة المشاركين',
                        't_en' => 'Delegate data, badges and on-site desk.', 't_ar' => 'بيانات المشاركين والبطاقات ومكتب الاستقبال.'],
                    ['l_en' => 'Interpretation & translation', 'l_ar' => 'الترجمة الفورية والتحريرية',
                        't_en' => 'Booths, equipment and interpreters as required.', 't_ar' => 'الكبائن والمعدّات والمترجمون حسب الحاجة.'],
                    ['l_en' => 'Security clearance & permits', 'l_ar' => 'التصاريح الأمنية والحكومية',
                        't_en' => 'Government permits and clearances for the Event.', 't_ar' => 'التصاريح والموافقات الحكومية اللازمة للفعالية.'],
                    ['l_en' => 'Quality assurance', 'l_ar' => 'ضمان الجودة',
                        't_en' => 'Brand compliance across every deliverable.', 't_ar' => 'الالتزام بالهوية المؤسسية في جميع المخرجات.'],
                ],
            ],

            // 1b · EXCLUSIONS — what the price does not cover
            [
                'n' => '1b', 'type' => 'bullets',
                'en_title' => 'Exclusions', 'ar_title' => 'الاستثناءات',
                'en' => ['Unless expressly stated in the Scope of Work, the following are not included in this Agreement:'],
                'ar' => ['ما لم يُنَص عليه صراحةً في نطاق العمل، فإن ما يلي غير مشمول بهذه الاتفاقية:'],
                'items' => [
                    ['l_en' => 'Anything not listed in the Scope of Work', 'l_ar' => 'أي بند غير مدرج في نطاق العمل', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Delegate international travel & visas', 'l_ar' => 'سفر المشاركين الدولي والتأشيرات', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Third-party speaker, content or sponsorship fees', 'l_ar' => 'أتعاب المتحدّثين أو المحتوى أو الرعاية من الغير', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Beverages and liquor where quoted separately', 'l_ar' => 'المشروبات والكحوليات حيثما تُسعَّر بشكل منفصل', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Costs arising from changes requested after written approval', 'l_ar' => 'التكاليف الناشئة عن تغييرات مطلوبة بعد الموافقة الخطّية', 't_en' => '', 't_ar' => ''],
                    ['l_en' => 'Taxes, duties or government fees beyond those stated herein', 'l_ar' => 'الضرائب أو الرسوم الحكومية الزائدة عمّا ورد في هذه الاتفاقية', 't_en' => '', 't_ar' => ''],
                ],
            ],

            // 2 · PROJECT PLAN & BUDGET
            [
                'n' => '2', 'type' => 'prose',
                'en_title' => 'Contract Value', 'ar_title' => 'قيمة العقد',
                'en' => array_values(array_filter([
                    $isFixed
                        ? "The total contract value for the scope of work set out in Clause 1 is {$money}. This is a fixed lump-sum price and shall not vary save by a written variation signed by both parties."
                        : "The estimated total value for the scope of work set out in Clause 1 is {$money}. This figure is an estimate prepared in good faith; the final amount may increase or decrease according to the final headcount, supplier availability and confirmed specifications.",
                    'Any addition to, or reduction of, the scope of work shall be agreed in writing and priced as a written variation before the relevant costs are committed.',
                    'The Event shall be executed in accordance with the project plan approved by the Client and tracked through Elite Business Hub’s project-management system.',
                ])),
                'ar' => array_values(array_filter([
                    $isFixed
                        ? "تبلغ قيمة العقد الإجمالية مقابل نطاق العمل المبيّن في البند (1) مبلغ {$money}. وهي قيمة إجمالية ثابتة لا تتغيّر إلا بموجب تعديل خطّي موقّع من الطرفين."
                        : "تبلغ القيمة الإجمالية التقديريّة مقابل نطاق العمل المبيّن في البند (1) مبلغ {$money}. وهذه القيمة تقديريّة أُعِدّت بحسن نيّة؛ وقد يزيد المبلغ النهائي أو ينقص تبعاً للعدد النهائي للحضور، وتوافر المورّدين، والمواصفات المعتمدة.",
                    'يُتَّفق خطياً على أي إضافة إلى نطاق العمل أو انتقاص منه، ويُسعَّر ذلك بموجب تعديل خطّي قبل الالتزام بالتكاليف ذات الصلة.',
                    'تُنفَّذ الفعالية وفقاً لخطة المشروع المعتمَدة من العميل، ويجري تتبّعها من خلال نظام إدارة المشاريع الخاص بإيليت بزنس هَب.',
                ])),
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
                'en' => ['This Agreement, together with the approved project plan and budget, constitutes the entire agreement between the parties. Any amendment must be made in writing and signed by both parties.'],
                'ar' => ['تشكّل هذه الاتفاقية، مع خطة المشروع والموازنة المعتمَدتين، الاتفاقَ الكامل بين الطرفين. ويجب أن يتمّ أي تعديل كتابةً وبتوقيع الطرفين.'],
            ],
        ];
    }

    /**
     * Further standard conditions appended after the core clauses. Seeded into
     * every new contract and fully editable from there.
     */
    public static function extraClauses(array $d): array
    {
        $prevailing = ($d['meta']['prevailing_language'] ?? 'ar') === 'en' ? 'English' : 'Arabic';
        $prevailingAr = $prevailing === 'English' ? 'الإنجليزي' : 'العربي';

        return [
            [
                'type' => 'prose',
                'en_title' => 'Insurance & Liability', 'ar_title' => 'التأمين والمسؤولية',
                'en' => [
                    'Elite Business Hub shall exercise reasonable professional care in delivering the Event and shall require its suppliers to hold the insurance customary for their trade.',
                    'Neither party shall be liable to the other for indirect or consequential loss, including loss of profit or reputation. The aggregate liability of Elite Business Hub under this Agreement shall not exceed the total amounts actually paid to it by the Client under this Agreement.',
                    'The Client shall be responsible for the conduct of its own guests, delegates and invitees, and for any damage caused by them to the venue or to third-party property.',
                ],
                'ar' => [
                    'تبذل إيليت بزنس هَب العناية المهنية المعقولة في تنفيذ الفعالية، وتُلزِم مورّديها بالاحتفاظ بالتأمينات المتعارف عليها في مجال عملهم.',
                    'لا يُسأل أي من الطرفين تجاه الآخر عن الأضرار غير المباشرة أو التبعية، بما في ذلك فوات الربح أو الإضرار بالسمعة. ولا تتجاوز المسؤولية الإجمالية لإيليت بزنس هَب بموجب هذه الاتفاقية إجمالي المبالغ المدفوعة لها فعلياً من العميل بموجبها.',
                    'يكون العميل مسؤولاً عن تصرّفات ضيوفه والمشاركين والمدعوّين من قِبَله، وعن أي ضرر يُلحقونه بالقاعة أو بممتلكات الغير.',
                ],
            ],
            [
                'type' => 'prose',
                'en_title' => 'Intellectual Property & Media Rights', 'ar_title' => 'الملكية الفكرية والحقوق الإعلامية',
                'en' => [
                    'Each party retains ownership of its own trademarks, logos and pre-existing materials. Each party grants the other a limited licence to use those marks solely for the purpose of promoting and delivering the Event.',
                    'Designs, concepts and creative materials produced by Elite Business Hub for the Event shall transfer to the Client upon settlement of all amounts due under this Agreement.',
                    'Elite Business Hub may photograph and film the Event and use such material for its portfolio and marketing, unless the Client notifies it otherwise in writing before the Event.',
                ],
                'ar' => [
                    'يحتفظ كل طرف بملكية علاماته التجارية وشعاراته وموادّه السابقة. ويمنح كل طرف الآخر ترخيصاً محدوداً باستخدام تلك العلامات لغرض الترويج للفعالية وتنفيذها حصراً.',
                    'تنتقل ملكية التصاميم والأفكار والمواد الإبداعية التي تُنتجها إيليت بزنس هَب للفعالية إلى العميل عند سداد كامل المبالغ المستحقّة بموجب هذه الاتفاقية.',
                    'يجوز لإيليت بزنس هَب تصوير الفعالية فوتوغرافياً وفيديوياً واستخدام تلك المواد في أعمالها التعريفية والتسويقية، ما لم يُشعِرها العميل خطياً بخلاف ذلك قبل الفعالية.',
                ],
            ],
            [
                'type' => 'prose',
                'en_title' => 'Health, Safety & Security', 'ar_title' => 'الصحة والسلامة والأمن',
                'en' => [
                    'Elite Business Hub shall deliver the Event in accordance with the health, safety and security requirements of the venue and of the competent authorities, and shall obtain the security clearances and government permits required for the Event.',
                    'The Client shall provide participant data required for security clearance sufficiently in advance. Any delay in providing such data that results in refused or late clearance shall not constitute a breach by Elite Business Hub.',
                ],
                'ar' => [
                    'تنفّذ إيليت بزنس هَب الفعالية وفقاً لمتطلّبات الصحة والسلامة والأمن الخاصة بالقاعة وبالجهات المختصّة، وتتولّى استصدار التصاريح الأمنية والحكومية اللازمة للفعالية.',
                    'يلتزم العميل بتزويد بيانات المشاركين اللازمة للتصاريح الأمنية في وقت كافٍ مسبقاً. ولا يُعَدّ أي تأخير في تقديم تلك البيانات يؤدّي إلى رفض التصريح أو تأخّره إخلالاً من جانب إيليت بزنس هَب.',
                ],
            ],
            [
                'type' => 'prose',
                'en_title' => 'Termination', 'ar_title' => 'إنهاء الاتفاقية',
                'en' => [
                    'Either party may terminate this Agreement by written notice if the other party commits a material breach and fails to remedy it within fourteen (14) days of written notice.',
                    'On termination for any reason, the Client shall pay for all services delivered up to the date of termination, together with all costs committed to third parties which cannot be recovered, and the cancellation charges set out in the Cancellation & Refund Policy clause.',
                ],
                'ar' => [
                    'يجوز لأيٍّ من الطرفين إنهاء هذه الاتفاقية بإشعار خطّي إذا ارتكب الطرف الآخر إخلالاً جوهرياً ولم يعالجه خلال أربعة عشر (14) يوماً من الإشعار الخطّي.',
                    'عند الإنهاء لأي سبب، يلتزم العميل بسداد قيمة جميع الخدمات المنفَّذة حتى تاريخ الإنهاء، إضافةً إلى كافة التكاليف الملتزَم بها تجاه الغير وغير القابلة للاسترداد، ورسوم الإلغاء المنصوص عليها في بند سياسة الإلغاء والاسترداد.',
                ],
            ],
            [
                'type' => 'prose',
                'en_title' => 'Assignment & Subcontracting', 'ar_title' => 'التنازل والتعاقد من الباطن',
                'en' => ['Neither party may assign this Agreement without the other’s written consent. Elite Business Hub may subcontract parts of the services to qualified suppliers and shall remain responsible to the Client for their performance.'],
                'ar' => ['لا يجوز لأيٍّ من الطرفين التنازل عن هذه الاتفاقية دون موافقة خطّية من الطرف الآخر. ويجوز لإيليت بزنس هَب التعاقد من الباطن على أجزاء من الخدمات مع مورّدين مؤهّلين، وتبقى مسؤولةً تجاه العميل عن أدائهم.'],
            ],
            [
                'type' => 'prose',
                'en_title' => 'Notices', 'ar_title' => 'الإشعارات',
                'en' => ['All notices under this Agreement shall be in writing and sent to the representatives and addresses stated in this Agreement, by hand, courier or email. Notice by email shall be deemed received on the next business day.'],
                'ar' => ['تكون جميع الإشعارات بموجب هذه الاتفاقية خطّية وتُرسَل إلى الممثّلين والعناوين المبيّنة في هذه الاتفاقية، باليد أو بالبريد السريع أو بالبريد الإلكتروني. ويُعَدّ الإشعار بالبريد الإلكتروني مستلَماً في يوم العمل التالي.'],
            ],
            [
                'type' => 'prose',
                'en_title' => 'Governing Language', 'ar_title' => 'لغة الاتفاقية',
                'en' => ["This Agreement is executed in English and Arabic. In the event of any discrepancy between the two texts, the {$prevailing} text shall prevail."],
                'ar' => ["حُرِّرت هذه الاتفاقية باللغتين الإنجليزية والعربية. وفي حال وجود أي تعارض بين النصّين، يُعتدّ بالنصّ {$prevailingAr}."],
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
                'en' => array_values($c['en'] ?? []),
                'ar' => array_values($c['ar'] ?? []),
                'items' => array_values($c['items'] ?? []),
                'rows' => array_values($c['rows'] ?? []),
            ];
        }

        return $out;
    }
}
