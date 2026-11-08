<?php

declare(strict_types=1);

namespace Tests\Unit\HotelMice;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\DTO\MiceProposalRequestDTO;
use Modules\Hotel\Domain\DTO\MiceProposalResponseDTO;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use Modules\Hotel\Domain\Repositories\HotelMiceReportingRepository;
use Modules\Hotel\Domain\Sagas\HotelMiceBookingOrchestrationSaga;
use Modules\Hotel\Domain\Sagas\HotelMiceCancellationSaga;
use Modules\Hotel\Domain\Sagas\HotelWeddingContractExecutionSaga;
use Modules\Hotel\Domain\Services\HotelBanquetBeveragePouringAuditService;
use Modules\Hotel\Domain\Services\HotelLuxuryWeddingConciergeService;
use Modules\Hotel\Domain\Services\HotelMiceContractAddendumManagerService;
use Modules\Hotel\Domain\Services\HotelMiceMasterFolioBillingService;
use Modules\Hotel\Domain\ValueObjects\BanquetRecipeUnit;
use Modules\Hotel\Domain\ValueObjects\BoothSpatialDimension;
use Modules\Hotel\Domain\ValueObjects\MiceAttritionPolicy;
use Modules\Hotel\Domain\ValueObjects\MilestoneEscalationSchedule;
use Modules\Hotel\Domain\ValueObjects\ProposalQuotationTimelock;
use Modules\Hotel\Domain\ValueObjects\UtilityConsumptionRate;
use Tests\TestCase;

final class HotelMiceSagasAndServicesTest extends TestCase
{
    use RefreshDatabase;

    protected HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-TEST-PROP-01',
            'name' => 'Grand Palace Hotel & Convention',
            'property_type' => 'RESORT',
            'city' => 'Jakarta',
            'total_rooms' => 500,
        ]);
    }

    public function test_value_objects_business_logic(): void
    {
        // Attrition Policy
        $policy = new MiceAttritionPolicy(15.0, 100.0);
        $penalty = $policy->calculatePenalty(100, 70, 1000000.0);
        $this->assertEquals(15000000, $penalty);

        // Recipe Unit
        $unit = new BanquetRecipeUnit('kg', 0.85);
        $this->assertEquals(1.1765, $unit->convertToStandardBatch(1.0));

        // Booth Dimensions
        $booth = new BoothSpatialDimension(3.0, 3.0, 2.5);
        $this->assertEquals(9.0, $booth->areaSquareMeters());
        $this->assertEquals(22.5, $booth->cubicVolumeMeters());
        $this->assertTrue($booth->isStandardModule3x3());

        // Utility Consumption
        $utility = new UtilityConsumptionRate(2500.0, 150000.0, 12000.0, 50000.0);
        $surcharge = $utility->computeSurcharge(100.0, 2.0, 5.0, 1.0);
        $this->assertEquals(660000.0, $surcharge);

        // Milestone Escalation Schedule
        $scheduleVo = new MilestoneEscalationSchedule;
        $eventDate = new DateTimeImmutable('2026-12-31');
        $milestones = $scheduleVo->generateSchedule(100000000, $eventDate);
        $this->assertCount(4, $milestones);
        $this->assertEquals(20000000, $milestones[0]['amount']);
        $this->assertEquals(30000000, $milestones[1]['amount']);

        // Quotation Timelock
        $issued = new DateTimeImmutable('2026-11-08 10:00:00');
        $timelock = new ProposalQuotationTimelock($issued, 72);
        $this->assertEquals('2026-11-11 10:00:00', $timelock->getExpiryTime()->format('Y-m-d H:i:s'));
        $this->assertFalse($timelock->isExpired(new DateTimeImmutable('2026-11-09 10:00:00')));
        $this->assertTrue($timelock->isExpired(new DateTimeImmutable('2026-11-12 10:00:00')));
    }

    public function test_dtos_serialization(): void
    {
        $dto = new MiceProposalRequestDTO(
            leadId: 'LEAD-001',
            clientName: 'Global Tech Expo',
            eventType: 'exhibition',
            startDate: '2026-12-01',
            endDate: '2026-12-05',
            attendeeCount: 1500,
            targetBudget: 250000000
        );
        $arr = $dto->toArray();
        $this->assertEquals('LEAD-001', $arr['lead_id']);

        $resDto = new MiceProposalResponseDTO(
            proposalId: 'PROP-001',
            proposalNumber: 'PR-2026-001',
            clientName: 'Global Tech',
            totalRevenue: 250000000,
            totalDirectCost: 120000000,
            grossMarginAmount: 130000000,
            grossMarginPercent: 52.0,
            isMarginGuardApproved: true,
            expiresAt: '2026-11-15'
        );
        $this->assertTrue($resDto->isMarginGuardApproved);
    }

    public function test_mice_booking_orchestration_saga_success(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-SAGA-001',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'Tech Summit Corp',
            'event_date' => '2026-12-01',
            'contract_total_value_idr' => 150000000,
            'status' => 'QUOTED',
        ]);

        $saga = new HotelMiceBookingOrchestrationSaga;
        $result = $saga->execute([
            'contract_id' => $contract->id,
            'rooms_count' => 30,
            'catering_pax' => 150,
            'booth_numbers' => ['A01', 'A02'],
            'credit_limit' => 50000000,
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(2, $result['booth_count']);
        $this->assertDatabaseHas('htl_room_block_allotments', [
            'mice_contract_id' => $contract->id,
            'allotted_rooms_count' => 30,
        ]);
        $this->assertDatabaseHas('htl_banquet_beo_orders', [
            'mice_contract_id' => $contract->id,
            'guaranteed_pax' => 150,
        ]);
        $this->assertDatabaseHas('htl_exhibition_booth_reservations', [
            'mice_contract_id' => $contract->id,
            'booth_number' => 'A01',
        ]);
    }

    public function test_mice_cancellation_saga(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-CANCEL-001',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'Fintech Asia',
            'event_date' => date('Y-m-d', strtotime('+15 days')), // < 30 days notice
            'contract_total_value_idr' => 100000000,
            'status' => 'CONFIRMED',
        ]);

        RoomBlockAllotment::create([
            'id' => (string) Str::uuid(),
            'block_code' => 'BLK-CANCEL-01',
            'mice_contract_id' => $contract->id,
            'check_in_date' => date('Y-m-d', strtotime('+15 days')),
            'release_deadline_date' => date('Y-m-d'),
            'allotted_rooms_count' => 20,
            'confirmed_rooms_count' => 5,
            'released_rooms_count' => 0,
            'status' => 'HELD',
        ]);

        $saga = new HotelMiceCancellationSaga;
        $res = $saga->cancel($contract->id, 'Force majeure cancellation');

        $this->assertEquals('cancelled', $res['status']);
        $this->assertEquals(100000000, $res['penalty_amount']); // 100% penalty
        $this->assertEquals(0.0, $res['refund_amount']);
        $this->assertEquals(15, $res['rooms_released']);

        $this->assertDatabaseHas('htl_mice_contracts', [
            'id' => $contract->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_wedding_milestone_saga_and_concierge(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-WED-001',
            'property_id' => $this->property->id,
            'event_type' => 'WEDDING',
            'client_name' => 'Romeo & Juliet Wedding',
            'event_date' => date('Y-m-d', strtotime('+120 days')),
            'contract_total_value_idr' => 200000000,
            'status' => 'CONFIRMED',
        ]);

        $schedule = HtlWeddingMilestoneSchedule::create([
            'id' => (string) Str::uuid(),
            'mice_contract_id' => $contract->id,
            'milestone_index' => 1,
            'name' => 'Booking Deposit & Venue Hold',
            'percentage' => 50.0,
            'amount_idr' => 100000000,
            'paid_amount_idr' => 50000000, // 50% paid of milestone
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'status' => 'PENDING',
        ]);

        $saga = new HotelWeddingContractExecutionSaga;
        $advRes = $saga->advanceMilestone($schedule->id);
        $this->assertEquals(50.0, $advRes['paid_ratio']);
        $this->assertCount(2, $advRes['unlocked_perks']);

        $concierge = new HotelLuxuryWeddingConciergeService;
        $coord = $concierge->coordinateWeddingVendors($schedule->id, [
            'decorator_company' => 'Royal Romance Decor',
            'sound_av_vendor' => 'Acoustic Elite',
        ]);
        $this->assertEquals('Royal Romance Decor', $coord['vendor_dossier']['decorator']);
    }

    public function test_beverage_pouring_audit_service(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-WINE-001',
            'property_id' => $this->property->id,
            'event_type' => 'GALA_DINNER',
            'client_name' => 'Sommelier Gala',
            'event_date' => date('Y-m-d'),
            'contract_total_value_idr' => 50000000,
            'status' => 'CONFIRMED',
        ]);

        $order = HtlBanquetOrder::create([
            'id' => (string) Str::uuid(),
            'beo_code' => 'BEO-WINE-001',
            'mice_contract_id' => $contract->id,
            'title' => 'Wine Tasting Gala',
            'meal_period' => 'DINNER',
            'guaranteed_pax' => 100,
            'prepared_pax' => 105,
            'event_date' => date('Y-m-d'),
            'status' => 'CONFIRMED',
        ]);

        $auditService = new HotelBanquetBeveragePouringAuditService;
        $logs = [
            [
                'item' => 'Cabernet Sauvignon',
                'bottles_opened' => 20, // 20 * 5 = 100 theoretical glasses
                'glasses_poured' => 102, // 2% variance, nominal
                'bottle_volume_ml' => 750.0,
                'standard_pour_ml' => 150.0,
            ],
        ];

        $report = $auditService->auditPouringVariance($order->id, $logs);
        $this->assertTrue($report['is_within_compliance']);
        $this->assertEquals(100, $report['total_theoretical_glasses']);
        $this->assertEquals(102, $report['total_actual_glasses']);
    }

    public function test_mice_contract_addendum_and_master_folio_billing(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-ADD-001',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'ASEAN Energy Forum',
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'contract_total_value_idr' => 100000000,
            'status' => 'CONFIRMED',
        ]);

        $addendumManager = new HotelMiceContractAddendumManagerService;
        $addendum = $addendumManager->issueAddendum($contract->id, [
            'addendum_type' => 'overtime_venue',
            'additional_cost' => 15000000,
            'reason' => 'Ballroom rental extended by 3 hours',
        ]);

        $this->assertEquals(115000000, $addendum['new_total']);
        $this->assertEquals(115000000, (int) $contract->fresh()->contract_total_value_idr);

        // Test Master Folio Billing Service
        $billingService = new HotelMiceMasterFolioBillingService;
        $charges = [
            ['type' => 'av_extra', 'description' => 'LED Video Wall Rental', 'amount' => 20000000, 'billed_to' => 'master'],
            ['type' => 'room_minibar', 'description' => 'VIP Minibar consumption', 'amount' => 1500000.0, 'billed_to' => 'guest_vip_1'],
        ];
        $payments = [
            ['type' => 'bank_wire', 'amount' => 135000000.0, 'reference' => 'WIRE-998877'],
        ];

        $billing = $billingService->calculateMasterBilling($contract->id, $charges, $payments);
        $this->assertEquals(135000000.0, $billing->totalMasterCharges);
        $this->assertEquals(1500000.0, $billing->totalIndividualCharges);
        $this->assertEquals(135000000.0, $billing->totalCreditsDeposits);
        $this->assertEquals(1500000.0, $billing->netBalanceDue);
    }

    public function test_mice_reporting_repository(): void
    {
        $repo = new HotelMiceReportingRepository;
        $summary = $repo->getExecutiveMiceSummary();
        $this->assertArrayHasKey('total_leads', $summary);
        $this->assertArrayHasKey('total_contract_value', $summary);
        $this->assertArrayHasKey('overall_pickup_rate', $summary);

        $pace = $repo->getRoomBlockPickupPace();
        $this->assertIsArray($pace);

        $pipeline = $repo->getMonthlyRevenuePipeline();
        $this->assertCount(12, $pipeline);
    }
}
