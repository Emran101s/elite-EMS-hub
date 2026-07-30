<?php

namespace Tests\Feature;

use App\Livewire\Hub\ContractTab;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\EventSpeaker;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ContractClauses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventContractTest extends TestCase
{
    use RefreshDatabase;

    private function make(): array
    {
        // Editing a contract needs 'manage-contract' (manager or above).
        $user = User::create(['name' => 'PM', 'email' => 'pm@ebh.test', 'password' => bcrypt('x'), 'role' => 'manager']);
        $event = Event::create(['name' => 'Test Summit', 'type' => 'conference', 'city' => 'Amman', 'country' => 'Jordan', 'starts_at' => now(), 'status' => 'planning', 'budget_cents' => 35000000, 'currency' => 'JOD']);

        return [$user, $event];
    }

    /** Mount the tab and open the client contract — the Deck is the landing now. */
    private function tab(User $user, Event $event)
    {
        $contract = EventContract::forEvent($event);

        return Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $contract->id);
    }

    public function test_body_seeds_as_editable_bilingual_blocks(): void
    {
        [$user, $event] = $this->make();

        $c = $this->tab($user, $event);
        $blocks = $c->get('data')['blocks'];

        // Standard set: the core clauses plus the appended conditions.
        $this->assertGreaterThanOrEqual(18, count($blocks));
        foreach ($blocks as $b) {
            $this->assertNotSame('', $b['title_en'], 'every clause has an English title');
            $this->assertNotSame('', $b['title_ar'], 'every clause has an Arabic title');
        }

        $titles = array_column($blocks, 'title_en');
        foreach (['Scope of Services', 'Exclusions', 'Contract Value', 'Financial Responsibility',
            'Payment Terms', 'Cancellation and Refunds', 'Taxes and Government Fees',
            'Roles and Responsibilities', 'Confidentiality', 'Force Majeure',
            'Governing Law and Dispute Resolution', 'Amendments and Entire Agreement',
            'Insurance and Liability', 'Intellectual Property and Media Rights',
            'Health, Safety and Security', 'Termination', 'Assignment and Subcontracting',
            'Notices', 'Governing Language',
            // Adopted from the India agreement — the four it had and we did not.
            'Acceptance of Services', 'Client Materials and Warranties',
            'Term of the Agreement'] as $expected) {
            $this->assertContains($expected, $titles);
        }
    }

    public function test_clauses_can_be_edited_added_reordered_and_deleted(): void
    {
        [$user, $event] = $this->make();

        $c = $this->tab($user, $event);
        $first = $c->get('data')['blocks'][0]['id'];
        $before = count($c->get('data')['blocks']);

        $c->call('updateBlockField', $first, 'title_en', 'Scope (edited)')
            ->call('updateParagraph', $first, 'ar', 0, 'نص معدّل')
            ->call('addBlock');

        $blocks = $c->get('data')['blocks'];
        $this->assertSame($before + 1, count($blocks));
        $this->assertSame('Scope (edited)', $blocks[0]['title_en']);
        $this->assertSame('نص معدّل', $blocks[0]['ar'][0]);

        // Reorder, then remove the section we added.
        $c->call('moveBlock', $first, 1);
        $this->assertSame('Scope (edited)', $c->get('data')['blocks'][1]['title_en']);

        $added = end($blocks);
        $c->call('deleteBlock', $added['id']);
        $this->assertSame($before, count($c->get('data')['blocks']));

        // Edits belong to this contract, not the standard set.
        $this->assertSame('Scope of Services', ContractClauses::blocks([])[0]['title_en']);
    }

    public function test_blank_percentages_do_not_break_the_tab_or_the_pdf(): void
    {
        [$user, $event] = $this->make();

        // Clearing a number input leaves "" in the JSON — this used to crash the
        // tab with "Unsupported operand types: int + string".
        $contract = EventContract::forEvent($event);
        $data = $contract->data;
        $data['financials']['payment_schedule'][0]['pct'] = '';
        $data['second_parties'][0]['share'] = '';
        $contract->data = $data;
        $contract->save();

        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])->assertOk();

        $this->actingAs($user)->get(route('events.contract.pdf', $event))->assertOk();

        // Any save normalises the blanks to real numbers so they can't spread.
        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $contract->id)
            ->call('setValueMode', 'fixed');

        $fresh = $contract->fresh()->data;
        $pct = $fresh['financials']['payment_schedule'][0]['pct'];
        $share = $fresh['second_parties'][0]['share'];

        $this->assertTrue(is_numeric($pct), 'pct is stored as a number, never ""');
        $this->assertTrue(is_numeric($share), 'share is stored as a number, never ""');
        $this->assertEquals(0, $pct);
        $this->assertEquals(0, $share);
    }

    public function test_scope_and_exclusions_are_editable_bullet_lists(): void
    {
        [$user, $event] = $this->make();

        $c = $this->tab($user, $event);
        $blocks = collect($c->get('data')['blocks']);

        $scope = $blocks->firstWhere('title_en', 'Scope of Services');
        $excl = $blocks->firstWhere('title_en', 'Exclusions');

        $this->assertSame('bullets', $scope['type']);
        $this->assertSame('bullets', $excl['type']);
        $this->assertNotEmpty($scope['items'], 'scope ships with deliverables you can edit');
        $this->assertNotEmpty($excl['items']);

        // Every bullet carries both languages.
        foreach ($scope['items'] as $it) {
            $this->assertNotSame('', $it['l_en']);
            $this->assertNotSame('', $it['l_ar']);
        }

        // Add, edit and remove a deliverable.
        $count = count($scope['items']);
        $c->call('addItem', $scope['id'])
            ->call('updateItem', $scope['id'], $count, 'l_en', 'Drone filming')
            ->call('updateItem', $scope['id'], $count, 'l_ar', 'التصوير بالطائرة المسيّرة');

        $after = collect($c->get('data')['blocks'])->firstWhere('id', $scope['id']);
        $this->assertSame($count + 1, count($after['items']));
        $this->assertSame('Drone filming', $after['items'][$count]['l_en']);
        $this->assertSame('التصوير بالطائرة المسيّرة', $after['items'][$count]['l_ar']);

        $c->call('removeItem', $scope['id'], $count);
        $this->assertSame($count, count(collect($c->get('data')['blocks'])->firstWhere('id', $scope['id'])['items']));
    }

    public function test_nothing_is_written_until_save_and_discard_reverts(): void
    {
        [$user, $event] = $this->make();
        $contract = EventContract::forEvent($event);

        $c = $this->tab($user, $event)
            ->assertSet('dirty', false);

        $first = $c->get('data')['blocks'][0]['id'];
        $c->call('updateBlockField', $first, 'title_en', 'Pending edit')
            ->assertSet('dirty', true);

        // Still untouched on disk.
        $this->assertSame('Scope of Services', $contract->fresh()->data['blocks'][0]['title_en']);

        // Discard throws the edit away and reloads what is stored.
        $c->call('discard')->assertSet('dirty', false);
        $this->assertSame('Scope of Services', $c->get('data')['blocks'][0]['title_en']);

        // Edit again and commit.
        $c->call('updateBlockField', $first, 'title_en', 'Committed edit')->call('save')
            ->assertSet('dirty', false);
        $this->assertSame('Committed edit', $contract->fresh()->data['blocks'][0]['title_en']);
    }

    public function test_a_floating_save_bar_follows_the_editor_however_far_you_scroll(): void
    {
        [$user, $event] = $this->make();

        // The editor is long; the top Save button scrolls out of reach. A bar
        // entangled to `dirty` sits fixed at the bottom so Save is always there.
        $html = $this->tab($user, $event)->html();

        $this->assertStringContainsString('x-show="dirty"', $html, 'the floating bar is wired to dirty');
        $this->assertStringContainsString('fixed inset-x-0 bottom-6', $html, 'and is pinned to the viewport, not the page');
    }

    public function test_parties_and_installments_can_be_added_and_removed(): void
    {
        [$user, $event] = $this->make();
        $contract = EventContract::forEvent($event);

        $c = $this->tab($user, $event);
        $parties = count($c->get('data')['second_parties']);
        $rows = count($c->get('data')['financials']['payment_schedule']);

        // A third funding entity, and a schedule of three instead of four.
        $c->call('addSecondParty')
            ->set('data.second_parties.'.$parties.'.name_en', 'Third Entity')
            ->call('removeInstallment', 3)
            ->call('balanceInstallments')
            ->call('save');

        $stored = $contract->fresh()->data;
        $this->assertCount($parties + 1, $stored['second_parties']);
        $this->assertSame('Third Entity', $stored['second_parties'][$parties]['name_en']);
        $this->assertCount($rows - 1, $stored['financials']['payment_schedule']);

        // Balancing leaves the schedule at exactly 100%.
        $total = collect($stored['financials']['payment_schedule'])->sum(fn ($s) => (float) $s['pct']);
        $this->assertEqualsWithDelta(100, $total, 0.001);
    }

    public function test_contract_value_is_independent_of_the_event_budget(): void
    {
        [$user, $event] = $this->make();

        $c = $this->tab($user, $event)
            ->call('setContractValue', '425000.500')
            ->call('save');

        $this->assertSame(42500050, $c->get('data')['financials']['contract_value_cents']);

        // Moving the event budget must not move the agreed contract figure.
        $event->update(['budget_cents' => 99900000]);
        $fresh = $this->tab($user, $event->fresh());
        $this->assertSame(42500050, $fresh->get('data')['financials']['contract_value_cents']);

        // ...unless you explicitly pull it across.
        $fresh->call('syncBudget')->call('save');
        $this->assertSame(99900000, $fresh->get('data')['financials']['contract_value_cents']);
    }

    public function test_contract_seeds_with_parties_and_financials(): void
    {
        [, $event] = $this->make();

        $c = EventContract::forEvent($event);

        $this->assertStringStartsWith('EBH-CTR-', $c->reference);
        $this->assertCount(2, $c->data['second_parties']);
        $this->assertSame(80, $c->data['second_parties'][0]['share']);
        $this->assertSame(20, $c->data['second_parties'][1]['share']);
        $this->assertSame(35000000, $c->data['financials']['estimated_total_cents']);
        // payment schedule totals 100%
        $this->assertSame(100, collect($c->data['financials']['payment_schedule'])->sum('pct'));
    }

    public function test_clauses_render_bilingual_with_variables(): void
    {
        [, $event] = $this->make();
        $c = EventContract::forEvent($event);

        $clauses = ContractClauses::clauses($c->data);
        $recitals = ContractClauses::recitals($c->data);

        // scope of services present in both languages
        $this->assertSame('Scope of Services', $clauses[0]['en_title']);
        $this->assertSame('نطاق الخدمات', $clauses[0]['ar_title']);
        $this->assertNotEmpty($recitals['ar']);

        // Cost sharing appears because this Client is two funding entities, and
        // carries their shares.
        $cost = collect($clauses)->firstWhere('type', 'costshare');
        $this->assertSame(80, $cost['rows'][0]['share']);

        // The value is spelled out beside the figure, as a contract must.
        $value = collect($clauses)->firstWhere('en_title', 'Contract Value');
        $this->assertStringContainsString('JOD 350,000.00', $value['en'][0]);
        $this->assertStringContainsString('Three Hundred Fifty Thousand Jordanian Dinars Only', $value['en'][0]);
        $this->assertStringContainsString('ديناراً أردنياً', $value['ar'][0]);

        // Rates the articles quote come from data.terms, not from the prose.
        $tax = collect($clauses)->firstWhere('en_title', 'Taxes and Government Fees');
        $this->assertStringContainsString('16% sales tax (VAT)', $tax['en'][1]);
    }

    /**
     * Four clauses adopted from the India agreement, which was structured
     * around things ours had no words for: a deadline after which unanswered
     * work counts as accepted, a warranty on the materials the client hands
     * over, a term, and an amicable-settlement step before court.
     */
    public function test_the_adopted_clauses_carry_their_operative_terms(): void
    {
        [, $event] = $this->make();
        $clauses = collect(ContractClauses::clauses(EventContract::forEvent($event)->data));

        // Acceptance: the period is a setting, and it appears in both columns.
        $acc = $clauses->firstWhere('en_title', 'Acceptance of Services');
        $this->assertStringContainsString('5 (5) business days', $acc['en'][1]);
        $this->assertStringContainsString('٥ (5)', $acc['ar'][1]);
        $this->assertStringContainsString('deemed accepted in full', $acc['en'][2]);

        // Client materials: the warranty, the indemnity and the takedown right.
        $mat = $clauses->firstWhere('en_title', 'Client Materials and Warranties');
        $this->assertStringContainsString('warrants', $mat['en'][0]);
        $this->assertStringContainsString('indemnify', $mat['en'][1]);
        $this->assertStringContainsString('suspend, withdraw or remove', $mat['en'][2]);

        // Term: survives, and says what survives.
        $term = $clauses->firstWhere('en_title', 'Term of the Agreement');
        $this->assertStringContainsString('until the Parties have fully performed', $term['en'][0]);
        $this->assertStringContainsString('survive', $term['en'][1]);

        // Negotiate before litigating.
        $law = $clauses->firstWhere('en_title', 'Governing Law and Dispute Resolution');
        $this->assertStringContainsString('amicably', $law['en'][1]);
        $this->assertStringContainsString('Failing amicable settlement', $law['en'][2]);
    }

    /** The acceptance window is a setting, not a sentence somebody retypes. */
    public function test_the_acceptance_window_follows_its_setting(): void
    {
        [, $event] = $this->make();
        $data = EventContract::forEvent($event)->data;
        $data['terms']['acceptance_days'] = 10;

        $acc = collect(ContractClauses::clauses($data))->firstWhere('en_title', 'Acceptance of Services');

        $this->assertStringContainsString('10 (10) business days', $acc['en'][1]);
        $this->assertStringContainsString('١٠ (10)', $acc['ar'][1]);
    }

    /**
     * An agreement that opens on "1. Scope of Services" is missing the words
     * that make it an agreement. The recitals were computed and never printed —
     * every contract the company sent went out without its preamble.
     */
    public function test_the_preamble_prints_on_the_document(): void
    {
        [$user, $event] = $this->make();
        $html = $this->tab($user, $event)->html();

        $this->assertStringContainsString('is entered into in', $html);
        $this->assertStringContainsString('First Party (Contractor)', $html);
        $this->assertStringContainsString('Second Party (Client)', $html);
        $this->assertStringContainsString('the Parties have agreed as follows', $html);
        $this->assertStringContainsString('الطرف الأول (المتعهّد)', $html);

        // And it reaches the export, not only the preview.
        $pdf = $this->actingAs($user)->get(route('events.contract.pdf', $event));
        $pdf->assertOk();
    }

    /**
     * Latin runs inside Arabic — a place, a date — are reordered by the
     * bidirectional algorithm and break across lines mid-phrase. "22 July 2026"
     * split over two lines of Arabic reads as two different things.
     */
    public function test_latin_runs_inside_the_arabic_preamble_are_isolated(): void
    {
        [, $event] = $this->make();
        $ar = ContractClauses::recitals(EventContract::forEvent($event)->data)['ar'][0];

        $this->assertStringContainsString("\u{2068}", $ar, 'the run is isolated');
        $this->assertStringContainsString("\u{2069}", $ar);
        $this->assertStringNotContainsString('Amman, Jordan', $ar, 'spaces became non-breaking');
        $this->assertStringContainsString("Amman,\u{00A0}Jordan", $ar);
    }

    /**
     * A bilingual contract is only bilingual if the two columns line up. Every
     * clause needs an Arabic title, every English paragraph needs the Arabic
     * paragraph that sits beside it, and every bullet needs its Arabic label —
     * a missing one prints as a blank half-row on the signed page.
     */
    public function test_every_clause_is_paired_in_both_languages(): void
    {
        [, $event] = $this->make();
        $blocks = ContractClauses::blocks(EventContract::forEvent($event)->data);

        $this->assertGreaterThanOrEqual(19, count($blocks), 'the full article set is seeded');

        foreach ($blocks as $b) {
            $this->assertNotSame('', trim($b['title_ar']), "{$b['title_en']} has no Arabic title");
            $this->assertSame(
                count($b['en']), count($b['ar']),
                "{$b['title_en']}: the English and Arabic paragraph counts differ",
            );

            foreach ($b['en'] as $i => $para) {
                $this->assertNotSame('', trim($para));
                $this->assertNotSame('', trim($b['ar'][$i]), "{$b['title_en']} ¶{$i} has no Arabic");
            }

            foreach ($b['items'] as $i => $item) {
                $this->assertNotSame('', trim($item['l_en']));
                $this->assertNotSame('', trim($item['l_ar']), "{$b['title_en']} bullet {$i} has no Arabic");
            }
        }
    }

    /**
     * One Client entity means no cost split to state — the article disappears
     * rather than printing a lone "100%" row that says nothing.
     */
    public function test_cost_sharing_only_appears_when_the_client_is_more_than_one_entity(): void
    {
        [, $event] = $this->make();
        $c = EventContract::forEvent($event);

        $data = $c->data;
        $data['second_parties'] = [array_merge($data['second_parties'][0], ['share' => 100])];

        $this->assertNull(collect(ContractClauses::clauses($data))->firstWhere('type', 'costshare'));
        $this->assertNotNull(collect(ContractClauses::clauses($c->data))->firstWhere('type', 'costshare'));
    }

    public function test_editing_persists(): void
    {
        [$user, $event] = $this->make();
        EventContract::forEvent($event);

        $this->tab($user, $event)
            ->set('data.second_parties.0.name_en', 'World People Assembly')
            ->set('data.second_parties.0.share', 75)
            ->set('data.second_parties.1.share', 25)
            ->assertSet('dirty', true)          // edits are pending, not written
            ->call('save')
            ->assertSet('dirty', false);

        $c = EventContract::where('event_id', $event->id)->first();
        $this->assertSame('World People Assembly', $c->data['second_parties'][0]['name_en']);
        $this->assertSame(75, $c->data['second_parties'][0]['share']);
    }

    public function test_pdf_downloads(): void
    {
        [$user, $event] = $this->make();

        $res = $this->actingAs($user)->get(route('events.contract.pdf', $event));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    /**
     * The live preview is the document — what it shows is what prints. Assert
     * the template actually reaches the page in both languages rather than
     * merely that the page did not crash.
     */
    public function test_the_live_preview_carries_the_template_in_both_languages(): void
    {
        [$user, $event] = $this->make();
        $html = $this->tab($user, $event)->html();

        foreach (['Scope of Services', 'Financial Responsibility', 'Payment Terms',
            'Governing Law and Dispute Resolution', 'Governing Language'] as $title) {
            $this->assertStringContainsString($title, $html, "the preview is missing “{$title}”");
        }

        foreach (['نطاق الخدمات', 'المسؤولية المالية', 'شروط الدفع', 'لغة الاتفاقية'] as $title) {
            $this->assertStringContainsString($title, $html, "the preview is missing the Arabic “{$title}”");
        }

        // A deliverable from the scope list, and the value spelled out.
        $this->assertStringContainsString('Preparation and submission of the final event report', $html);
        $this->assertStringContainsString('Three Hundred Fifty Thousand Jordanian Dinars Only', $html);
    }

    // ── Phase 1: many typed contracts ──────────────────────────

    public function test_the_existing_contract_migrates_in_as_a_bilingual_client_type(): void
    {
        [, $event] = $this->make();

        $c = EventContract::forEvent($event);
        $this->assertSame('client', $c->type);
        $this->assertTrue($c->isClient());
        $this->assertTrue($c->isBilingual());
        $this->assertTrue($c->isStructured());
        $this->assertStringStartsWith('EBH-CTR-', $c->reference, 'client keeps its original reference');
        $this->assertSame($c->id, $event->contract->id, 'the contract relation still resolves to the client one');
    }

    public function test_an_event_can_hold_several_typed_contracts(): void
    {
        [$user, $event] = $this->make();
        $supplier = Supplier::create(['name' => 'Elite Fleet Co', 'category' => 'transport']);
        $event->suppliers()->attach($supplier->id);

        $c = $this->tab($user, $event);

        // Opens on the client MSA, as it always did.
        $this->assertSame('client', $c->get('type'));
        $clientId = $c->get('contractId');

        // Create a vendor agreement tied to the supplier.
        $c->set('newType', 'vendor')->set('newPartyId', $supplier->id)->call('createContract');

        $vendor = $event->contracts()->where('type', 'vendor')->firstOrFail();
        $this->assertSame($supplier->id, $vendor->party_id);
        $this->assertSame(Supplier::class, $vendor->party_type);
        $this->assertSame('Vendor Agreement · Elite Fleet Co', $vendor->displayTitle());
        $this->assertSame($vendor->id, $c->get('contractId'), 'the editor switches to the new contract');
        $this->assertSame('vendor', $c->get('type'));

        // Switch back to the client contract.
        $c->call('selectContract', $clientId);
        $this->assertSame('client', $c->get('type'));

        $this->assertSame(2, $event->contracts()->count());
    }

    public function test_a_letter_needs_no_counterparty_and_is_not_structured(): void
    {
        [$user, $event] = $this->make();

        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->set('newType', 'letter')->call('createContract');

        $letter = $event->contracts()->where('type', 'letter')->firstOrFail();
        $this->assertNull($letter->party_id);
        $this->assertFalse($letter->isStructured());
        $this->assertFalse($letter->isBilingual());
    }

    public function test_typed_documents_open_with_real_bilingual_clause_bodies(): void
    {
        [, $event] = $this->make();
        $supplier = Supplier::create(['name' => 'Elite Fleet Co', 'category' => 'transport']);

        $vendor = EventContract::createFor($event, 'vendor', $supplier);
        $blocks = $vendor->data['blocks'];

        $this->assertGreaterThanOrEqual(7, count($blocks), 'a vendor agreement is not a blank page');
        foreach ($blocks as $b) {
            $this->assertNotSame('', $b['title_en']);
            $this->assertNotSame('', $b['title_ar'], 'every clause carries Arabic too');
        }

        // The counterparty and the event are interpolated into the text.
        $firstClause = implode(' ', $blocks[0]['en']);
        $this->assertStringContainsString('Elite Fleet Co', $firstClause);
        $this->assertStringContainsString($event->name, $firstClause);

        // Speaker fee flows into the honorarium clause when the speaker has one.
        $speaker = EventSpeaker::create(['event_id' => $event->id, 'name' => 'Dr Amal', 'fee_cents' => 250000]);
        $sp = EventContract::createFor($event, 'speaker', $speaker);
        $honorarium = collect($sp->data['blocks'])->firstWhere('title_en', 'Honorarium & Expenses');
        $this->assertStringContainsString('2,500', implode(' ', $honorarium['en']));

        // A letter opens as a courteous scaffold, not clauses.
        $letter = EventContract::createFor($event, 'letter');
        $this->assertCount(1, $letter->data['blocks']);
        $this->assertStringContainsString('Dear', $letter->data['blocks'][0]['en'][0]);
    }

    public function test_non_client_documents_export_through_the_shared_document_pdf(): void
    {
        [$user, $event] = $this->make();
        $vendor = EventContract::createFor($event, 'vendor', Supplier::create(['name' => 'Elite Fleet Co', 'category' => 'transport']));

        $res = $this->actingAs($user)->get(route('events.contract.doc.pdf', [$event, $vendor]));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));

        // A contract from another event is not reachable through this one.
        $other = Event::create(['name' => 'Other', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan', 'starts_at' => now()]);
        $foreign = EventContract::createFor($other, 'vendor');
        $this->actingAs($user)->get(route('events.contract.doc.pdf', [$event, $foreign]))->assertNotFound();
    }

    public function test_a_counterparty_can_be_picked_from_the_event_list_and_refills_the_agreement(): void
    {
        [$user, $event] = $this->make();
        $speaker = EventSpeaker::create([
            'event_id' => $event->id, 'name' => 'Dr Amal Al-Rashid',
            'topic' => 'AI in Events', 'fee_cents' => 250000, 'email' => 'amal@ebh.test',
        ]);

        // An agreement created WITHOUT a party…
        $doc = EventContract::createFor($event, 'speaker');
        $this->assertNull($doc->party_id);

        // …gets one picked from the event's speaker list.
        $c = Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $doc->id)
            ->call('setParty', $speaker->id);

        $doc->refresh();
        $this->assertSame($speaker->id, $doc->party_id, 'the link is written immediately');
        $this->assertSame('Dr Amal Al-Rashid', $c->get('data')['counterparty']['name_en']);
        $this->assertSame('AI in Events', $c->get('data')['counterparty']['detail']);
        $this->assertSame(250000, $c->get('data')['counterparty']['fee_cents']);
        $this->assertSame('Speaker Agreement · Dr Amal Al-Rashid', $c->get('title'));

        // Refill re-writes the body with the speaker woven into the clauses.
        $c->call('refillFromTemplate')->call('save');
        $body = implode(' ', $doc->fresh()->data['blocks'][0]['en']);
        $this->assertStringContainsString('Dr Amal Al-Rashid', $body);
        $this->assertStringContainsString('AI in Events', $body);
        $honorarium = collect($doc->fresh()->data['blocks'])->firstWhere('title_en', 'Honorarium & Expenses');
        $this->assertStringContainsString('2,500', implode(' ', $honorarium['en']));
    }

    public function test_a_letter_wears_a_professional_letterhead_not_an_agreement_masthead(): void
    {
        [$user, $event] = $this->make();
        CompanyProfile::firstOrCreate(['name' => 'Elite Business Hub'], [
            'phone' => '+962 6 000 0000', 'email' => 'hello@ebh.test', 'city' => 'Amman', 'country' => 'Jordan',
        ]);

        $letter = EventContract::createFor($event, 'letter');

        $html = Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $letter->id)
            ->set('data.counterparty.name_en', 'HE the Minister of Culture')
            ->html();

        // Letterhead: company identity, recipient block, date and reference.
        $this->assertStringContainsString('Elite Business Hub', $html);
        $this->assertStringContainsString('HE the Minister of Culture', $html);
        $this->assertStringContainsString($letter->reference, $html);
        $this->assertStringContainsString('Issued by', $html, 'a letter is issued, not counter-signed');

        // And no numbered clause chrome — a letter reads as prose.
        $this->assertStringNotContainsString('Untitled clause', $html);

        // The letter also exports through the shared paper.
        $this->actingAs($user)->get(route('events.contract.doc.pdf', [$event, $letter]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_deck_is_the_landing_and_creates_nothing_by_itself(): void
    {
        [$user, $event] = $this->make();

        // Mounting shows the wall of documents — no editor open, no contract
        // silently created behind your back.
        $c = Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])->assertOk();
        $this->assertNull($c->get('contractId'), 'the Deck is the landing, not an editor');
        $this->assertSame(0, $event->contracts()->count(), 'mount creates nothing');
        $c->assertSee('No documents yet');

        // Choosing a document opens the editor; going back returns to the Deck.
        $client = EventContract::forEvent($event);
        $c->call('selectContract', $client->id);
        $this->assertSame($client->id, $c->get('contractId'));

        $c->call('backToDeck');
        $this->assertNull($c->get('contractId'));
    }

    public function test_any_document_can_be_deleted_and_deleting_the_open_one_returns_to_the_deck(): void
    {
        [$user, $event] = $this->make();
        $client = EventContract::forEvent($event);
        $vendor = EventContract::createFor($event, 'vendor');

        $c = Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $vendor->id)
            ->call('deleteContract', $vendor->id);

        $this->assertNull($vendor->fresh());
        $this->assertNull($c->get('contractId'), 'deleting the open document returns you to the Deck');

        // The client contract is deletable too — deleted means deleted, and a
        // reload does not resurrect it.
        $c->call('deleteContract', $client->id);
        $this->assertNull($client->fresh());
        $this->assertSame(0, $event->contracts()->count());
        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event->fresh()])->assertOk();
        $this->assertSame(0, $event->contracts()->count(), 'mount does not auto-recreate the deleted contract');
    }

    public function test_editing_a_typed_contract_never_touches_the_client_one(): void
    {
        [$user, $event] = $this->make();
        $client = EventContract::forEvent($event);
        $vendor = EventContract::createFor($event, 'vendor');

        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $vendor->id)
            ->set('title', 'Catering — Al Diwan')
            ->call('save');

        $this->assertSame('Catering — Al Diwan', $vendor->fresh()->title);
        $this->assertSame('client', $client->fresh()->type, 'the client contract is untouched');
        $this->assertNull($client->fresh()->title);
    }

    public function test_the_deck_pipeline_moves_a_contract_across_statuses(): void
    {
        [$user, $event] = $this->make();
        $vendor = EventContract::createFor($event, 'vendor');
        $this->assertSame('draft', $vendor->status);
        $this->assertSame('draft', $vendor->pipelineColumn());
        $this->assertSame(0, $vendor->stageIndex());

        $c = $this->tab($user, $event);

        // Drag to "Sent" then "Signed" — the seal stamps and signed_at is set.
        $c->call('setContractStatus', $vendor->id, 'sent');
        $this->assertSame('sent', $vendor->fresh()->status);
        $this->assertSame(1, $vendor->fresh()->stageIndex());

        $c->call('setContractStatus', $vendor->id, 'signed');
        $vendor->refresh();
        $this->assertSame('signed', $vendor->status);
        $this->assertNotNull($vendor->signed_at, 'signing stamps the date');
        $this->assertSame(2, $vendor->stageIndex());

        // Back to draft clears the signature date.
        $c->call('setContractStatus', $vendor->id, 'draft');
        $this->assertNull($vendor->fresh()->signed_at);

        // A bogus status is ignored.
        $c->call('setContractStatus', $vendor->id, 'nonsense');
        $this->assertSame('draft', $vendor->fresh()->status);
    }

    public function test_a_read_only_user_cannot_change_a_contract_status(): void
    {
        [, $event] = $this->make();
        $vendor = EventContract::createFor($event, 'vendor');
        $viewer = User::create(['name' => 'V2', 'email' => 'v2@ebh.test', 'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(ContractTab::class, ['event' => $event])
            ->call('setContractStatus', $vendor->id, 'signed')
            ->assertForbidden();

        $this->assertSame('draft', $vendor->fresh()->status);
    }

    public function test_a_read_only_user_cannot_create_a_contract(): void
    {
        [, $event] = $this->make();
        EventContract::forEvent($event);
        $viewer = User::create(['name' => 'V', 'email' => 'v@ebh.test', 'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(ContractTab::class, ['event' => $event])
            ->set('newType', 'vendor')->call('createContract')
            ->assertForbidden();

        $this->assertSame(1, $event->contracts()->count(), 'only the client contract exists');
    }

    // ── Signatures ─────────────────────────────────────────────

    public function test_opening_a_contract_seeds_its_default_signatories(): void
    {
        [$user, $event] = $this->make();

        $c = $this->tab($user, $event);
        $contract = EventContract::forEvent($event);

        // Organiser + one per client second party.
        $roles = $contract->signatories()->pluck('role')->all();
        $this->assertContains('organiser', $roles);
        $this->assertContains('client', $roles);
        $this->assertGreaterThanOrEqual(2, count($roles));
        $c->assertOk();
    }

    public function test_recording_signatures_drives_the_status_and_writes_an_audit_trail(): void
    {
        [$user, $event] = $this->make();
        $contract = EventContract::forEvent($event);
        $contract->ensureSignatories();
        $sigs = $contract->signatories()->get();

        $c = $this->tab($user, $event);

        // First signature → partially signed.
        $c->call('recordSignature', $sigs[0]->id);
        $first = $sigs[0]->fresh();
        $this->assertNotNull($first->signed_at);
        $this->assertSame($first->name, $first->signature_data);
        $this->assertNotNull($first->signed_ip, 'the IP is recorded');
        $this->assertSame($contract->fresh()->contentHash(), $first->signed_hash, 'the document fingerprint is frozen');
        $this->assertSame('partially_signed', $contract->fresh()->status);

        // Sign the rest → fully signed, dated.
        foreach ($sigs->slice(1) as $s) {
            $c->call('recordSignature', $s->id);
        }
        $this->assertTrue($contract->fresh()->isFullySigned());
        $this->assertSame('signed', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->signed_at);

        // Undoing one drops it back to partially signed.
        $c->call('unsign', $sigs[0]->id);
        $this->assertSame('partially_signed', $contract->fresh()->status);
        $this->assertNull($sigs[0]->fresh()->signed_at);
    }

    public function test_signatories_can_be_added_and_removed(): void
    {
        [$user, $event] = $this->make();
        $contract = EventContract::forEvent($event);
        $contract->ensureSignatories();
        $before = $contract->signatories()->count();

        $c = $this->tab($user, $event)->call('addSignatory');

        $new = $contract->signatories()->latest('id')->first();
        $c->call('updateSignatory', $new->id, 'name', 'HE the Minister')
            ->call('updateSignatory', $new->id, 'role', 'witness');

        $new->refresh();
        $this->assertSame('HE the Minister', $new->name);
        $this->assertSame('witness', $new->role);
        $this->assertSame($before + 1, $contract->signatories()->count());

        $c->call('removeSignatory', $new->id);
        $this->assertSame($before, $contract->signatories()->count());
    }

    public function test_an_unnamed_party_cannot_be_marked_signed(): void
    {
        [$user, $event] = $this->make();
        $contract = EventContract::forEvent($event);
        $blank = $contract->signatories()->create(['role' => 'witness', 'name' => '', 'order' => 9]);

        $this->tab($user, $event)->call('recordSignature', $blank->id);

        $this->assertNull($blank->fresh()->signed_at, 'you cannot sign for a nameless party');
    }

    public function test_the_pdf_shows_a_signature_block_per_signatory_with_the_signed_ones_marked(): void
    {
        [$user, $event] = $this->make();
        $contract = EventContract::forEvent($event);
        $contract->ensureSignatories();
        $first = $contract->signatories()->first();
        $first->update([
            'signed_at' => now(), 'signature_data' => $first->name,
            'signed_hash' => $contract->contentHash(), 'signed_ip' => '127.0.0.1',
        ]);

        $res = $this->actingAs($user)->get(route('events.contract.pdf', $event));
        $res->assertOk()->assertHeader('content-type', 'application/pdf');

        // Assert on the source HTML so the check doesn't depend on PDF internals.
        $html = view('event-contract.contract', [
            'event' => $event, 'contract' => $contract, 'data' => $contract->data,
            'recitals' => ContractClauses::recitals($contract->data),
            'clauses' => [], 'signatories' => $contract->signatories()->get(), 'css' => '',
        ])->render();

        $this->assertStringContainsString($first->name, $html);
        $this->assertStringContainsString('Signed', $html, 'the signed block is marked');
        $this->assertStringContainsString('verify', $html, 'a verification fingerprint prints');
    }

    /**
     * The editor's modules are an accordion — one group owning one open panel —
     * rather than six independent booleans that can all be true at once. Assert
     * the wiring: every panel writes the same `at`, and none of them carries a
     * private `open` any more.
     */
    public function test_the_editor_modules_are_one_accordion(): void
    {
        [$user, $event] = $this->make();
        $html = $this->tab($user, $event)->html();

        $this->assertStringContainsString('at: null', $html, 'the group owns the open panel');
        $this->assertStringNotContainsString('{ open: false }', $html, 'no panel keeps its own state');

        foreach (['parties', 'value-and-payments', 'budget-assumptions', 'contract-body', 'signatories'] as $panel) {
            $this->assertStringContainsString("at = (at === '{$panel}'", $html, "{$panel} toggles the group");
            $this->assertStringContainsString("x-collapse.duration.300ms", $html);
        }
    }
}
