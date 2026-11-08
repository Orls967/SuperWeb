<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use App\Models\User;
use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Services\AquacultureBiosecurityProtocolService;
use Modules\Marine\Application\Services\AquacultureInsuranceUnderwritingService;
use Modules\Marine\Application\Services\AquacultureVesselColdChainLogisticsService;
use Modules\Marine\Application\Services\SilvofisheryCarbonOffsetLedgerService;
use Modules\Marine\Application\Services\SustainableFeedFormulationService;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\BlueCarbonCreditIssuance;
use Modules\Marine\Domain\Models\FeedInventoryBatch;
use Tests\TestCase;

class BiosecurityAndCarbonOffsettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_pre_stocking_biosecurity_audit_evaluates_readiness(): void
    {
        $service = new AquacultureBiosecurityProtocolService;

        $auditData = [
            'water_source' => 'DEEP_SEA_CANAL',
            'chlorine_disinfection_applied_ppm' => 30.0,
            'chlorine_neutralization_days' => 4,
            'residual_chlorine_ppm' => 0.005,
            'crab_crab_fence_installed' => true,
            'bird_scaring_device_active' => true,
            'reservoir_settling_days' => 4,
        ];

        $res = $service->auditPreStockingBiosecurity($auditData);

        $this->assertTrue($res['is_ready_for_stocking']);
        $this->assertGreaterThanOrEqual(85.0, $res['biosecurity_index']);
        $this->assertEquals('BIOSECURITY_LEVEL_3_CLEARED', $res['clearance_certificate']);
    }

    public function test_fifo_feed_ratio_calculation(): void
    {
        $feedService = new SustainableFeedFormulationService;
        $feed = FeedInventoryBatch::first();

        // Tepung ikan 12%, minyak ikan 3%, FCR 1.25 -> FIFO = max((12*1.25)/22.5, (3*1.25)/5.0) = max(0.667, 0.750) = 0.75
        $res = $feedService->calculateFifoRatio($feed->id, 12.0, 3.0, 1.25);

        $this->assertTrue($res['is_asc_compliant']);
        $this->assertEquals(0.75, $res['composite_fifo_score']);
        $this->assertEquals('EXCELLENT_LOW_MARINE_FOOTPRINT', $res['sustainability_rating']);
    }

    public function test_insurance_parametric_cover_binding(): void
    {
        $insService = new AquacultureInsuranceUnderwritingService;
        $batch = AquacultureBatch::first();

        $policy = $insService->bindParametricPondPolicy($batch->id, 5000000, 150000);

        $this->assertNotEmpty($policy['policy_reference']);
        $this->assertEquals(5000000, $policy['sum_insured_minor_cents']);
        $this->assertEquals('ACTIVE_PARAMETRIC_COVER', $policy['status']);
    }

    public function test_vessel_cold_chain_logistics_and_custody_verification(): void
    {
        $logistics = new AquacultureVesselColdChainLogisticsService;
        $user = User::first();
        $lot = AquacultureExportLot::first();

        $booking = $logistics->bookReeferLogisticsTransfer($user, $lot->id, 'IDSUB', 'Surabaya', 50000);
        $this->assertNotEmpty($booking['logistics_tracking_number']);
        $this->assertEquals('MULTIMODAL_CHILLED_VESSEL_REEFER', $booking['transport_mode']);

        $validHash = hash('sha256', 'VALID_OCEAN_BILL_OF_LADING');
        $this->assertTrue($logistics->verifyBillOfLadingHash($validHash));
    }

    public function test_carbon_insetting_offset_retirement(): void
    {
        $offsetService = new SilvofisheryCarbonOffsetLedgerService;
        $lot = AquacultureExportLot::first();
        $issuance = BlueCarbonCreditIssuance::first();

        $initialCredits = (float) $issuance->carbon_credits_tco2e;
        $retiredAmount = 5.5;

        $res = $offsetService->retireCreditsForLotInsetting($lot->id, $issuance->id, $retiredAmount);

        $this->assertTrue($res['net_zero_verified']);
        $this->assertEquals('INSETTING_RETIRED_PERMANENTLY', $res['offset_status']);
        $this->assertEquals(round($initialCredits - $retiredAmount, 2), $res['remaining_active_credits_tco2e']);
    }
}
