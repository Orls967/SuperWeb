<?php

declare(strict_types=1);

namespace Tests\Acceptance\HotelMice;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\AuditMiceEventReconciliationAction;
use Modules\Hotel\Application\Actions\ConvertProposalToMiceContractAction;
use Modules\Hotel\Application\Actions\CreateMiceProposalWithMarginGuardAction;
use Modules\Hotel\Application\Actions\GenerateBanquetBeoProductionSheetAction;
use Modules\Hotel\Application\Actions\ProcessWeddingMilestonePaymentAction;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HtlMiceLead;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use Modules\Hotel\Domain\Services\HotelRoomBlockAllotmentManagerService;
use Tests\TestCase;

class HotelMiceBanquetSalesAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected HotelProperty $property;

    protected User $salesUser;

    protected User $frontDeskUser;

    protected User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-MICE-01',
            'name' => 'The Royal Cliff Grand Ballroom & Resort',
            'property_type' => 'RESORT',
            'city' => 'Nusa Dua',
            'total_rooms' => 300,
        ]);

        $this->salesUser = User::factory()->create(['role' => 'sales_mgr']);
        $this->frontDeskUser = User::factory()->create(['role' => 'front_desk']);
        $this->financeUser = User::factory()->create(['role' => 'finance']);
    }

    /**
     * 114.6 (a): Staged Room Block Release (H-30, H-14, H-7) returns unpicked rooms to public inventory
     */
    public function test_01_staged_room_block_release_cutoff_invariants(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-TEST-001',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'World Economic Forum ASEAN',
            'event_date' => '2026-12-15',
            'contract_total_value_idr' => 1000000000,
            'status' => 'CONFIRMED',
        ]);

        $block = RoomBlockAllotment::create([
            'id' => (string) Str::uuid(),
            'block_code' => 'BLK-MICE-001',
            'mice_contract_id' => $contract->id,
            'check_in_date' => '2026-12-15',
            'release_deadline_date' => '2026-11-15',
            'allotted_rooms_count' => 200,
            'confirmed_rooms_count' => 60,
            'released_rooms_count' => 0,
            'status' => 'HELD',
        ]);

        $manager = app(HotelRoomBlockAllotmentManagerService::class);

        // Simulasi pada H-25 (Cutoff H-30 aktif): unpicked = 200 - 60 = 140. 25% = 35 kamar dilepaskan.
        $certH30 = $manager->processScheduledRelease($block, new DateTimeImmutable('2026-11-20'));
        $this->assertEquals(35, $certH30->roomsReleasedToPublic);
        $this->assertEquals(35, $block->fresh()->released_rooms_count);

        // Simulasi pickup delegasi bertambah 40 kamar
        $manager->recordPickup($block->fresh(), 40);
        $this->assertEquals(100, $block->fresh()->confirmed_rooms_count);

        // Simulasi pada H-10 (Cutoff H-14 aktif): sisa unpicked = (200 - 35) - 100 = 65. 50% = 32 kamar dilepaskan.
        $certH14 = $manager->processScheduledRelease($block->fresh(), new DateTimeImmutable('2026-12-05'));
        $this->assertEquals(32, $certH14->roomsReleasedToPublic);
        $this->assertEquals(35 + 32, $block->fresh()->released_rooms_count);

        // Simulasi pada H-5 (Final Cutoff H-7 aktif): 100% sisa unpicked dilepaskan
        $certH7 = $manager->processScheduledRelease($block->fresh(), new DateTimeImmutable('2026-12-10'));
        $this->assertGreaterThan(0, $certH7->roomsReleasedToPublic);
        $this->assertEquals('RELEASED', $block->fresh()->status);
    }

    /**
     * 114.6 (b): Banquet Event Order (BEO) BOM Recipe Explosion & Ingredient Breakdown
     */
    public function test_02_banquet_event_order_bom_recipe_explosion(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-TEST-002',
            'property_id' => $this->property->id,
            'event_type' => 'GALA_DINNER',
            'client_name' => 'International Bankers Gala',
            'event_date' => '2026-11-25',
            'contract_total_value_idr' => 250000000,
            'status' => 'CONFIRMED',
        ]);

        $action = app(GenerateBanquetBeoProductionSheetAction::class);
        $recipe = [
            [
                'item_id' => 'ING-BEEF-WAGYU',
                'name' => 'Wagyu Tenderloin',
                'type' => 'food',
                'portion_qty' => 200.0,
                'unit' => 'gram',
                'cost_per_unit_idr' => 300,
                'yield_loss_ratio' => 0.10,
            ],
            [
                'item_id' => 'BEV-CHAMPAGNE',
                'name' => 'Dom Pérignon 750ml',
                'type' => 'beverage',
                'portion_qty' => 150.0,
                'unit' => 'ml',
                'cost_per_unit_idr' => 1500000,
                'pour_shrinkage_ratio' => 0.05,
                'bottle_volume_ml' => 750,
            ],
        ];

        $result = $action->execute(
            contract: $contract,
            title: 'Royal Gala Banquet 300 Pax',
            mealPeriod: 'DINNER',
            guaranteedPax: 300,
            eventDate: '2026-11-25',
            recipeComponents: $recipe,
            safetyBufferRatio: 1.05,
            eventDurationHours: 4.0,
            hourlyLaborRateIdr: 50000
        );

        $this->assertEquals(315, $result['certificate']->preparedPax);
        $this->assertNotEmpty($result['order']->recipe_bom_json);
        $this->assertGreaterThan(0, $result['certificate']->totalFoodCogsIdr);
        $this->assertGreaterThan(0, $result['certificate']->totalBeverageCogsIdr);
        $this->assertEquals((15 + 8) * 4 * 50000, $result['certificate']->totalLaborCostIdr);
    }

    /**
     * 114.6 (c): Gross Margin 40% Guardrail Verification (Approve vs Reject)
     */
    public function test_03_event_gross_margin_guardrail_enforcement(): void
    {
        $lead = HtlMiceLead::create([
            'id' => (string) Str::uuid(),
            'lead_code' => 'LEAD-2026-001',
            'organizer_name' => 'Andi Wijaya',
            'company_name' => 'PT Maju Bersama',
            'email' => 'andi@majubersama.com',
            'phone' => '08123456789',
            'event_type' => 'CONFERENCE',
            'expected_pax' => 200,
            'estimated_budget_idr' => 150000000,
            'tentative_date' => '2026-12-20',
            'status' => 'NEW',
        ]);

        $action = app(CreateMiceProposalWithMarginGuardAction::class);

        // Case A: Margin 54.67% >= 40% -> Approved Offer
        $revApproved = [
            'hall_rental' => 50000000,
            'fnb' => 75000000,
            'av' => 15000000,
            'room' => 10000000,
        ];
        $costApproved = [
            'food_cogs' => 35000000,
            'bev_cogs' => 10000000,
            'labor' => 8000000,
            'av' => 15000000,
        ];

        $resApproved = $action->execute($lead, 'Proposal Conference A', $revApproved, $costApproved);
        $this->assertEquals('APPROVED_OFFER', $resApproved['proposal']->status);
        $this->assertTrue($resApproved['certificate']->isCompliant);

        // Case B: Margin 30% < 40% -> Guardrail Breach Rejected
        $revBreach = ['hall_rental' => 100000000];
        $costBreach = ['food_cogs' => 70000000];

        $resBreach = $action->execute($lead, 'Proposal Conference B Sub-Margin', $revBreach, $costBreach);
        $this->assertEquals('GUARDRAIL_BREACH', $resBreach['proposal']->status);
        $this->assertFalse($resBreach['certificate']->isCompliant);
        $this->assertGreaterThan(0, $resBreach['certificate']->priceShortfallIdr);
    }

    /**
     * 114.6 (d): Sequential Wedding Milestone Payments & Bridal Suite Complimentary Upgrade
     */
    public function test_04_wedding_milestone_payment_schedule_and_bridal_suite_privilege(): void
    {
        $lead = HtlMiceLead::create([
            'id' => (string) Str::uuid(),
            'lead_code' => 'LEAD-WED-001',
            'organizer_name' => 'Natasha Wilona',
            'company_name' => 'Citra Wedding Planner',
            'email' => 'natasha@citrawedding.com',
            'phone' => '08198765432',
            'event_type' => 'WEDDING',
            'expected_pax' => 500,
            'estimated_budget_idr' => 280000000,
            'tentative_date' => '2027-06-15',
            'status' => 'NEW',
        ]);

        $proposalAction = app(CreateMiceProposalWithMarginGuardAction::class);
        $proposal = $proposalAction->execute(
            lead: $lead,
            title: 'Royal Platinum Wedding',
            revenueBreakdown: ['fnb' => 200000000, 'hall_rental' => 80000000],
            costBreakdown: ['food_cogs' => 100000000, 'labor' => 20000000]
        )['proposal'];

        $convertAction = app(ConvertProposalToMiceContractAction::class);
        $contract = $convertAction->execute(
            proposal: $proposal,
            eventStartDate: '2027-06-15',
            eventEndDate: '2027-06-16',
            signingDate: '2026-11-08',
            roomBlockInitialRooms: 20
        );

        // Kontrak 280 Jt >= 250 Jt -> Bridal Suite Complimentary Upgrade Otomatis
        $this->assertTrue($contract->is_bridal_suite_complimentary);

        // Pastikan ada 4 milestone berurutan
        $milestones = $contract->weddingMilestones;
        $this->assertCount(4, $milestones);

        $paymentAction = app(ProcessWeddingMilestonePaymentAction::class);

        // Bayar Termin 1 (DP 20%)
        $m1 = $paymentAction->execute($contract, 1, $milestones[0]->amount_idr, 'PAY-REF-T1');
        $this->assertEquals('PAID', $m1->status);

        // Mencoba bayar Termin 3 sebelum Termin 2 lunas -> DomainException
        $this->expectException(\DomainException::class);
        $paymentAction->execute($contract, 3, $milestones[2]->amount_idr, 'PAY-REF-T3');
    }

    /**
     * 114.6 (e): Reconciliation of MICE Event Master Folio = Ledger Zero Discrepancy
     */
    public function test_05_mice_event_master_folio_ledger_reconciliation(): void
    {
        $lead = HtlMiceLead::create([
            'id' => (string) Str::uuid(),
            'lead_code' => 'LEAD-REC-001',
            'organizer_name' => 'Budi Santoso',
            'company_name' => 'PT Kaltim Daya Pasifik',
            'email' => 'budi@kaltimdaya.com',
            'phone' => '0811223344',
            'event_type' => 'CONFERENCE',
            'expected_pax' => 100,
            'estimated_budget_idr' => 100000000,
            'tentative_date' => '2026-11-30',
            'status' => 'NEW',
        ]);

        $proposal = app(CreateMiceProposalWithMarginGuardAction::class)->execute(
            lead: $lead,
            title: 'Reconciled Conference',
            revenueBreakdown: ['hall_rental' => 60000000, 'fnb' => 40000000],
            costBreakdown: ['food_cogs' => 30000000]
        )['proposal'];

        $contract = app(ConvertProposalToMiceContractAction::class)->execute(
            proposal: $proposal,
            eventStartDate: '2026-11-30',
            eventEndDate: '2026-12-01',
            signingDate: '2026-11-08'
        );

        $paymentAction = app(ProcessWeddingMilestonePaymentAction::class);
        $milestones = $contract->weddingMilestones;

        // Lunasi seluruh 4 termin secara berurutan
        foreach ($milestones as $ms) {
            $paymentAction->execute($contract, $ms->milestone_index, $ms->amount_idr, 'PAY-REF-MS-'.$ms->milestone_index);
        }

        $reconcileAction = app(AuditMiceEventReconciliationAction::class);
        $report = $reconcileAction->execute($contract);

        $this->assertTrue($report['is_balanced']);
        $this->assertEquals(0, $report['outstanding_balance_idr']);
        $this->assertEquals('RECONCILED', $contract->fresh()->status);
        $this->assertNotEmpty($report['audit_idempotency_key']);
    }
}
