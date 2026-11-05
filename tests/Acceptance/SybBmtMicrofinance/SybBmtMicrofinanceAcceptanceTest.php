<?php

declare(strict_types=1);

namespace Tests\Acceptance\SybBmtMicrofinance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Agri\Contracts\FarmerLoanOffset;
use Modules\Agri\Contracts\NdviIndexReader;
use Modules\Shared\Domain\Algorithms\Islamic\GigCreditLimit;
use Modules\Shared\Domain\Algorithms\Islamic\RepaymentWaterfall;
use Modules\Syariah\Application\Services\BmtMicrofinanceService;
use Modules\Syariah\Contracts\GigPayoutHistory;
use Tests\TestCase;

class SybBmtMicrofinanceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
    }

    public function test_bmt_cooperative_and_wadiah_savings(): void
    {
        $officer = User::factory()->create(['role' => 'syariah_officer']);

        // 1. Pendaftaran BMT
        $response = $this->actingAs($officer)->post('/syb/bmt/cooperatives', [
            'cooperative_code' => 'BMT-AL-AMANAH-01',
            'name' => 'BMT Al-Amanah Berkah',
            'legal_entity_number' => 'AHU-001234.AH.01.26',
            'city' => 'Surabaya',
            'dps_advisor' => 'Dr. Ahmad Shariah',
        ]);
        $response->assertStatus(201);

        // 2. Pembukaan Rekening Simpanan Wadiah Yad Dhamanah
        $cust = User::factory()->create(['role' => 'customer']);
        $saveResp = $this->actingAs($officer)->post('/syb/bmt/savings', [
            'cooperative_code' => 'BMT-AL-AMANAH-01',
            'customer_party_ref' => (string) $cust->id,
            'contract_type' => 'WADIAH_YAD_DHAMANAH',
            'initial_deposit_idr' => 50000000, // Rp 500.000
        ]);
        $saveResp->assertStatus(201);
    }

    public function test_micro_group_financing_mudharabah_bertingkat(): void
    {
        $officer = User::factory()->create(['role' => 'syariah_officer']);
        $cust = User::factory()->create(['role' => 'customer']);

        $service = app(BmtMicrofinanceService::class);
        $group = $service->createGroupFacility(
            cooperativeCode: 'BMT-AL-AMANAH-01',
            groupName: 'Kelompok Mawar Sejahtera',
            totalMembers: 5,
            facilityAmountIdr: 2500000000, // Rp 25.000.000
            shariahContract: 'MUDHARABAH_BERTINGKAT',
            profitNisbahShohibulMaalBps: 4000 // 40% BMT, 60% Nasabah
        );

        $this->assertNotNull($group);
        $this->assertEquals('Kelompok Mawar Sejahtera', $group->group_name);
        $this->assertTrue($group->group_liability_active);

        // Repayment waterfall
        $waterfall = RepaymentWaterfall::allocate(
            paymentAmount: 50000000,
            dueMargin: 10000000,
            duePrincipal: 40000000
        );
        $this->assertTrue($waterfall->isFullySettled);
    }

    public function test_gig_worker_credit_limit_and_contract_g7_k6(): void
    {
        $officer = User::factory()->create(['role' => 'syariah_officer']);
        $gigWorker = User::factory()->create(['role' => 'customer']);

        $gigService = app(GigPayoutHistory::class);
        $history = $gigService->getMonthlyPayoutHistory((string) $gigWorker->id, 6);
        $this->assertCount(6, $history);

        $amanahScore = $gigService->getAmanahScore((string) $gigWorker->id);
        $this->assertGreaterThanOrEqual(0, $amanahScore);

        $incomes = array_column($history, 'gross_amount');
        $calc = GigCreditLimit::calculate(
            workerPartyId: (string) $gigWorker->id,
            monthlyIncomes: $incomes,
            amanahScore: $amanahScore
        );

        $this->assertGreaterThan(0, $calc->maxCreditLimit);
    }

    public function test_agri_micro_salam_with_ndvi_and_loan_offset_g7_k1_k2(): void
    {
        $ndviReader = app(NdviIndexReader::class);
        $index = $ndviReader->readIndex('PLOT-PADI-01', now()->toImmutable());
        $this->assertArrayHasKey('ndvi_bps', $index);

        $loanOffset = app(FarmerLoanOffset::class);
        $farmerId = 'FARMER-TEST-UUID';
        $remaining = $loanOffset->offsetLoanBalance($farmerId, 50000000, 'CLM-AGRI-163');
        $this->assertIsInt($remaining);
    }

    public function test_social_impact_scoring_and_audit_microfinance(): void
    {
        $service = app(BmtMicrofinanceService::class);
        $metric = $service->recordSocialImpact(
            periodYear: 2026,
            jobsCreated: 42,
            mustahikToMuzakkiCount: 15,
            avgIncomeIncreaseBps: 2500
        );

        $this->assertEquals(2026, $metric->period_year);
        $this->assertEquals(42, $metric->jobs_created);

        // Verifikasi command syb:audit
        $exitCode = Artisan::call('syb:audit');
        $this->assertSame(0, $exitCode);
    }
}
