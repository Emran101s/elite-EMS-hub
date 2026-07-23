<?php

namespace App\Support;

use App\Models\Event;

/**
 * The default clause bodies for every non-client document type — what a vendor,
 * speaker, sponsorship or letter opens with instead of a blank page.
 *
 * Same block shape as ContractClauses, and the same ownership rule: templates
 * seed a contract ONCE at creation, then the contract owns its own text.
 * Figures and names known at creation (counterparty, fee, package, dates) are
 * interpolated into the text; everything stays editable afterwards.
 */
class ContractTemplates
{
    /** @return array<int,array<string,mixed>> */
    public static function blocks(string $type, array $d): array
    {
        $clauses = match ($type) {
            'vendor' => self::vendor($d),
            'speaker' => self::speaker($d),
            'sponsorship' => self::sponsorship($d),
            'letter' => self::letter($d),
            default => [],
        };

        $out = [];
        $n = 0;

        foreach ($clauses as $c) {
            $n++;
            $out[] = [
                'id' => 'b'.$n,
                'type' => $c['type'] ?? 'prose',
                'title_en' => $c['en_title'] ?? '',
                'title_ar' => $c['ar_title'] ?? '',
                'en' => array_values($c['en'] ?? []),
                'ar' => array_values($c['ar'] ?? []),
                'items' => array_values($c['items'] ?? []),
                'rows' => [],
            ];
        }

        return $out;
    }

    // ── shared interpolations ───────────────────────────────────

    private static function vars(array $d): array
    {
        $fee = $d['counterparty']['fee_cents'] ?? null;

        return [
            'event' => $d['event']['name'] ?? 'the Event',
            'dates' => $d['event']['dates'] ?? 'the agreed dates',
            'where' => $d['event']['location'] ?? 'Amman, Jordan',
            'who' => ($d['counterparty']['name_en'] ?? '') !== '' ? $d['counterparty']['name_en'] : 'the Counterparty',
            'fee' => $fee ? Event::moneyIn((int) $fee, $d['currency'] ?? 'JOD') : null,
            'package' => $d['counterparty']['package'] ?? null,
            'detail' => $d['counterparty']['detail'] ?? null,
        ];
    }

    // ── vendor ──────────────────────────────────────────────────

    private static function vendor(array $d): array
    {
        $v = self::vars($d);

        return [
            ['en_title' => 'Engagement & Scope', 'ar_title' => 'التعاقد ونطاق العمل',
                'en' => ['Elite Business Hub engages '.$v['who'].' to supply the '.($v['detail'] ? $v['detail'].' ' : '')."services and deliverables agreed for {$v['event']} ({$v['dates']}, {$v['where']}), as detailed in the accepted quotation or purchase order, which forms part of this Agreement."],
                'ar' => ['تتعاقد إيليت بزنس هَب مع '.$v['who'].' لتوريد خدمات ومخرجات '.($v['detail'] ? '('.$v['detail'].') ' : '')."المتفق عليها لفعالية {$v['event']} ({$v['dates']}، {$v['where']})، وفقاً لما هو مفصّل في عرض السعر أو أمر الشراء المعتمد، والذي يُعدّ جزءاً لا يتجزأ من هذه الاتفاقية."]],
            ['en_title' => 'Deliverables & Standards', 'ar_title' => 'المخرجات ومعايير التنفيذ',
                'en' => ['All deliverables shall conform to the agreed specifications, be of professional quality, and be delivered on the agreed schedule. Time is of the essence for event-day deliverables.'],
                'ar' => ['يجب أن تُطابق جميع المخرجات المواصفات المتفق عليها وأن تكون بجودة احترافية وأن تُسلَّم وفق الجدول الزمني المتفق عليه، ويُعدّ الالتزام بالمواعيد جوهرياً لمخرجات يوم الفعالية.']],
            ['en_title' => 'Payment Terms', 'ar_title' => 'شروط الدفع',
                'en' => ['Fees are as per the accepted quotation, inclusive of delivery, labour and all supplier costs unless stated otherwise. Correct invoices are settled within thirty (30) days of satisfactory delivery.'],
                'ar' => ['تكون الأتعاب وفق عرض السعر المعتمد، شاملةً التوصيل والعمالة وكافة تكاليف المورّد ما لم يُنص على خلاف ذلك، وتُسدَّد الفواتير الصحيحة خلال ثلاثين (30) يوماً من التسليم المُرضي.']],
            ['en_title' => 'Delay & Substitution', 'ar_title' => 'التأخير والاستبدال',
                'en' => ['If the Supplier fails to deliver on time or to standard, Elite Business Hub may reduce the fees proportionally and, after notice, source substitute goods or services at the Supplier’s reasonable cost.'],
                'ar' => ['في حال إخفاق المورّد في التسليم في الموعد أو بالمعيار المطلوب، يحق لإيليت بزنس هَب تخفيض الأتعاب بشكل تناسبي، وبعد الإخطار، تدبير بدائل على نفقة المورّد المعقولة.']],
            ['en_title' => 'Insurance & Liability', 'ar_title' => 'التأمين والمسؤولية',
                'en' => ['The Supplier is responsible for its personnel, equipment and third-party liability arising from its work, and shall hold any insurances required by law for its activity.'],
                'ar' => ['يتحمّل المورّد المسؤولية عن موظفيه ومعداته وعن مسؤولية الغير الناشئة عن أعماله، ويلتزم بحيازة التأمينات التي يفرضها القانون على نشاطه.']],
            ['en_title' => 'Confidentiality', 'ar_title' => 'السرية',
                'en' => ['All information about the Event, the client and the guests is confidential and may not be used or disclosed beyond performing this Agreement.'],
                'ar' => ['تُعدّ جميع المعلومات المتعلقة بالفعالية والعميل والضيوف سرّية، ولا يجوز استخدامها أو الإفصاح عنها خارج نطاق تنفيذ هذه الاتفاقية.']],
            ['en_title' => 'Cancellation', 'ar_title' => 'الإلغاء',
                'en' => ['Elite Business Hub may cancel by written notice. Work properly performed and costs irrevocably committed up to the notice date are payable; no further liability arises.'],
                'ar' => ['يجوز لإيليت بزنس هَب الإلغاء بإخطار خطي، على أن تُدفع قيمة الأعمال المنجزة أصولاً والتكاليف الملتزم بها نهائياً حتى تاريخ الإخطار، دون أي مسؤولية إضافية.']],
            ['en_title' => 'Governing Law', 'ar_title' => 'القانون الواجب التطبيق',
                'en' => ['This Agreement is governed by the laws of the Hashemite Kingdom of Jordan; disputes fall to the competent courts of Amman.'],
                'ar' => ['تخضع هذه الاتفاقية لقوانين المملكة الأردنية الهاشمية، وتختص محاكم عمّان بالنظر في أي نزاع.']],
        ];
    }

    // ── speaker ─────────────────────────────────────────────────

    private static function speaker(array $d): array
    {
        $v = self::vars($d);
        $fee = $v['fee']
            ? "An honorarium of {$v['fee']} is payable within thirty (30) days after the Event, subject to fulfilment of the Speaker’s obligations."
            : 'The honorarium, if any, is as agreed in writing and payable within thirty (30) days after the Event, subject to fulfilment of the Speaker’s obligations.';
        $feeAr = $v['fee']
            ? "تُدفع مكافأة قدرها {$v['fee']} خلال ثلاثين (30) يوماً بعد الفعالية، شريطة وفاء المتحدث بالتزاماته."
            : 'تكون المكافأة، إن وُجدت، وفق ما يُتفق عليه خطياً، وتُدفع خلال ثلاثين (30) يوماً بعد الفعالية شريطة وفاء المتحدث بالتزاماته.';

        return [
            ['en_title' => 'Engagement', 'ar_title' => 'التعاقد',
                'en' => ["{$v['who']} (the “Speaker”) agrees to participate in {$v['event']} ({$v['dates']}, {$v['where']}) and to deliver the agreed session(s)".($v['detail'] ? " on “{$v['detail']}”" : '').' as scheduled by the organiser.'],
                'ar' => ["يوافق {$v['who']} («المتحدث») على المشاركة في {$v['event']} ({$v['dates']}، {$v['where']}) وتقديم الجلسة أو الجلسات المتفق عليها".($v['detail'] ? " حول «{$v['detail']}»" : '').' وفق جدول المنظّم.']],
            ['en_title' => 'Honorarium & Expenses', 'ar_title' => 'المكافأة والنفقات',
                'en' => [$fee.' Travel and accommodation are provided or reimbursed only as expressly agreed.'],
                'ar' => [$feeAr.' ولا تُوفَّر نفقات السفر والإقامة أو تُسترد إلا وفق اتفاق صريح.']],
            ['en_title' => 'Speaker Obligations', 'ar_title' => 'التزامات المتحدث',
                'en' => ['The Speaker shall provide session titles, abstracts and presentation materials by the agreed deadlines, attend technical rehearsals as requested, and keep to the allocated timings.'],
                'ar' => ['يلتزم المتحدث بتقديم عناوين الجلسات والملخصات والمواد العرضية في المواعيد المتفق عليها، وحضور البروفات الفنية عند الطلب، والتقيّد بالأوقات المخصصة.']],
            ['en_title' => 'Content & Intellectual Property', 'ar_title' => 'المحتوى والملكية الفكرية',
                'en' => ['The Speaker retains ownership of their materials and warrants they infringe no third-party rights. The organiser is granted a non-exclusive licence to record, photograph, publish and promote the session in connection with the Event.'],
                'ar' => ['يحتفظ المتحدث بملكية مواده ويضمن عدم مساسها بحقوق الغير، ويمنح المنظّم ترخيصاً غير حصري بتسجيل الجلسة وتصويرها ونشرها والترويج لها في سياق الفعالية.']],
            ['en_title' => 'Recording & Media', 'ar_title' => 'التسجيل والإعلام',
                'en' => ['The Speaker consents to the session being recorded, streamed and made available by the organiser, with appropriate attribution.'],
                'ar' => ['يوافق المتحدث على تسجيل الجلسة وبثّها وإتاحتها من قِبل المنظّم مع نسبة المحتوى إليه على النحو اللائق.']],
            ['en_title' => 'Cancellation & Substitution', 'ar_title' => 'الإلغاء والاستبدال',
                'en' => ['Either party may withdraw for reasons beyond reasonable control with prompt notice. If the Speaker withdraws otherwise, the organiser may cancel any honorarium and recover non-recoverable costs incurred in reliance on this Agreement.'],
                'ar' => ['يجوز لأي من الطرفين الانسحاب لأسباب خارجة عن الإرادة المعقولة مع الإخطار الفوري. أما إذا انسحب المتحدث لغير ذلك، فيجوز للمنظّم إلغاء المكافأة واسترداد التكاليف غير القابلة للاسترداد التي تكبّدها اعتماداً على هذه الاتفاقية.']],
            ['en_title' => 'Governing Law', 'ar_title' => 'القانون الواجب التطبيق',
                'en' => ['This Agreement is governed by the laws of the Hashemite Kingdom of Jordan.'],
                'ar' => ['تخضع هذه الاتفاقية لقوانين المملكة الأردنية الهاشمية.']],
        ];
    }

    // ── sponsorship ─────────────────────────────────────────────

    private static function sponsorship(array $d): array
    {
        $v = self::vars($d);
        $tier = $v['package'] ? " under the “{$v['package']}” package" : '';
        $tierAr = $v['package'] ? " ضمن باقة «{$v['package']}»" : '';
        $fee = $v['fee']
            ? "The sponsorship fee is {$v['fee']}, payable per the agreed schedule and in any case in full before the Event begins."
            : 'The sponsorship fee is as agreed in writing, payable per the agreed schedule and in any case in full before the Event begins.';
        $feeAr = $v['fee']
            ? "تبلغ قيمة الرعاية {$v['fee']}، تُدفع وفق الجدول المتفق عليه وبكامل قيمتها قبل بدء الفعالية في جميع الأحوال."
            : 'تكون قيمة الرعاية وفق ما يُتفق عليه خطياً، وتُدفع وفق الجدول المتفق عليه وبكامل قيمتها قبل بدء الفعالية في جميع الأحوال.';

        return [
            ['en_title' => 'Sponsorship Grant', 'ar_title' => 'منح الرعاية',
                'en' => ["The organiser grants {$v['who']} (the “Sponsor”) sponsorship of {$v['event']} ({$v['dates']}, {$v['where']}){$tier}, with the benefits listed in the agreed package description."],
                'ar' => ["يمنح المنظّم {$v['who']} («الراعي») رعاية فعالية {$v['event']} ({$v['dates']}، {$v['where']}){$tierAr}، مع المزايا المدرجة في وصف الباقة المتفق عليها."]],
            ['en_title' => 'Sponsorship Fee', 'ar_title' => 'قيمة الرعاية',
                'en' => [$fee],
                'ar' => [$feeAr]],
            ['en_title' => 'Branding Rights', 'ar_title' => 'حقوق العلامة التجارية',
                'en' => ['Each party may use the other’s name and logo solely in connection with the Event, per supplied brand guidelines, with artwork approved in advance by the owning party.'],
                'ar' => ['يجوز لكل طرف استخدام اسم وشعار الطرف الآخر في سياق الفعالية حصراً، وفق أدلة الهوية المزوَّدة، وبعد اعتماد التصاميم مسبقاً من الطرف المالك.']],
            ['en_title' => 'Delivery of Benefits', 'ar_title' => 'تقديم المزايا',
                'en' => ['The organiser shall deliver the listed benefits with reasonable skill and care. Where a listed benefit becomes impracticable, a substitute of at least equal value will be provided.'],
                'ar' => ['يقدّم المنظّم المزايا المدرجة بمهارة وعناية معقولتين، وإذا تعذّر تقديم إحدى المزايا عملياً، يُوفَّر بديل بقيمة معادلة على الأقل.']],
            ['en_title' => 'Exclusivity', 'ar_title' => 'الحصرية',
                'en' => ['No category exclusivity is granted unless expressly agreed in writing.'],
                'ar' => ['لا تُمنح أي حصرية على مستوى الفئة ما لم يُتفق على ذلك خطياً وبشكل صريح.']],
            ['en_title' => 'Cancellation', 'ar_title' => 'الإلغاء',
                'en' => ['If the Sponsor withdraws, fees already due remain payable and paid amounts are retained to the extent of benefits delivered and costs committed. If the Event is cancelled by the organiser, amounts paid for undelivered benefits are refunded.'],
                'ar' => ['في حال انسحاب الراعي، تبقى المبالغ المستحقة واجبة الدفع وتُحتجز المبالغ المدفوعة بقدر المزايا المقدَّمة والتكاليف الملتزم بها. وإذا ألغى المنظّم الفعالية، تُرد المبالغ المدفوعة مقابل المزايا غير المقدَّمة.']],
            ['en_title' => 'Governing Law', 'ar_title' => 'القانون الواجب التطبيق',
                'en' => ['This Agreement is governed by the laws of the Hashemite Kingdom of Jordan.'],
                'ar' => ['تخضع هذه الاتفاقية لقوانين المملكة الأردنية الهاشمية.']],
        ];
    }

    // ── letter ──────────────────────────────────────────────────

    private static function letter(array $d): array
    {
        $v = self::vars($d);
        $to = ($d['counterparty']['name_en'] ?? '') !== '' ? $d['counterparty']['name_en'] : '[Recipient]';

        return [
            ['en_title' => 'Letter', 'ar_title' => 'رسالة',
                'en' => ["Dear {$to},", "On behalf of Elite Business Hub, it is our pleasure to write to you regarding {$v['event']}, taking place {$v['dates']} in {$v['where']}.", 'We would be honoured by your favourable consideration, and remain at your disposal for any clarification.', 'With our highest regards,'],
                'ar' => ["حضرة {$to} المحترم،", "يسعدنا في إيليت بزنس هَب أن نتوجّه إليكم بهذه الرسالة بخصوص {$v['event']}، المقرر إقامتها بتاريخ {$v['dates']} في {$v['where']}.", 'ونتشرف بكريم موافقتكم، ونبقى رهن إشارتكم لأي استفسار.', 'وتفضلوا بقبول فائق الاحترام،']],
        ];
    }
}
