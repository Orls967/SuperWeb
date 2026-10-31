<?php

declare(strict_types=1);

namespace Tests\Acceptance\FinAsset;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Insurance\Application\Services\InsurancePolicyService;
use Modules\Insurance\Application\Services\InsurTechClaimsService;
use Modules\Insurance\Application\Services\ParametricOracleService;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Modules\Rwa\Application\Services\RwaDividendService;
use Modules\Rwa\Application\Services\RwaInvestorService;
use Modules\Rwa\Application\Services\RwaOfferingService;
use Modules\Rwa\Application\Services\RwaOrderbookService;
use Modules\Rwa\Application\Services\RwaTokenService;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaDividend;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Ledger\RwaAccounts;
use Modules\Wealth\Application\Services\CashSweepService;
use Modules\Wealth\Application\Services\PortfolioManagementService;
use Modules\Wealth\Application\Services\RoboAdvisorService;
use Tests\TestCase;

/**
 * L4.4: 180-Day Continuous Deterministic Simulation for FinAsset MVP.
 * Simulates real-world lifecycle across RWA Tokenization, InsurTech Parametric Policies,
 * and Wealth Robo-Advisory Cash Sweep & Rebalancing.
 */
class FinAsset180DaySimulationTest extends TestCase
{
    use RefreshDatabase;

    protected SimClockInterface $simClock;

    protected LedgerService $ledgerService;

    protected User $issuer;

    protected User $complianceOfficer;

    protected User $underwriter;

    /** @var list<User> */
    protected array $investors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->simClock = app(SimClockInterface::class);
        $this->ledgerService = app(LedgerService::class);

        $startTime = Carbon::parse('2026-01-01 08:00:00', 'UTC');
        $this->simClock->initRun('FinAsset180dSim', $startTime, 42);

        $this->issuer = User::factory()->create(['role' => 'asset_manager']);
        $this->complianceOfficer = User::factory()->create(['role' => 'admin']);
        $this->underwriter = User::factory()->create(['role' => 'underwriter']);

        $this->issuer->walletAccount('IDR');
        $this->depositCash($this->issuer, 500_000_000, 'dep:issuer');

        $this->investors = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = User::factory()->create(['role' => 'investor']);
            $user->walletAccount('IDR');
            $this->depositCash($user, 1_000_000_000, "dep:inv:{$i}");
            $this->investors[] = $user;
        }
    }

    protected function tearDown(): void
    {
        $this->simClock->reset();
        parent::tearDown();
    }

    public function test_180_day_continuous_finasset_lifecycle_simulation(): void
    {
        $rwaTokenService = app(RwaTokenService::class);
        $rwaInvestorService = app(RwaInvestorService::class);
        $rwaOfferingService = app(RwaOfferingService::class);
        $rwaOrderbookService = app(RwaOrderbookService::class);
        $rwaDividendService = app(RwaDividendService::class);

        $policyService = app(InsurancePolicyService::class);
        $claimsService = app(InsurTechClaimsService::class);

        $roboAdvisorService = app(RoboAdvisorService::class);
        $sweepService = app(CashSweepService::class);
        $portfolioService = app(PortfolioManagementService::class);

        // ==========================================
        // DAY 1: Asset Creation, Approval & KYC
        // ==========================================
        $draftAsset = $rwaTokenService->createDraftAsset([
            'symbol' => 'RWA-HOTEL180',
            'name' => 'Grand FinAsset Hotel 180',
            'underlying_asset_type' => 'hotel',
            'underlying_asset_id' => 'HOTEL-180',
            'appraisal_value_idr' => 100_000_000,
            'total_supply_tokens' => 10_000,
            'creator_id' => $this->issuer->id,
        ], idempotencyKey: 'sim:rwa:draft:001');

        $approvedAsset = $rwaTokenService->approveAsset($draftAsset->id, $this->complianceOfficer->id, idempotencyKey: 'sim:rwa:appr:001');
        $issuedAsset = $rwaTokenService->issueAsset($approvedAsset->id, idempotencyKey: 'sim:rwa:iss:001');

        foreach ($this->investors as $idx => $inv) {
            $rwaInvestorService->verifyInvestor(
                userId: $inv->id,
                kycDocumentId: 'KYC-DOC-'.($idx + 1),
                approvalId: 'APPR-KYC-'.($idx + 1),
                validUntil: now()->addYears(2),
                idempotencyKey: 'sim:kyc:'.($idx + 1)
            );
        }

        $offering = $rwaOfferingService->createOffering([
            'rwa_asset_id' => $issuedAsset->id,
            'offering_code' => 'IPO-HOTEL180',
            'price_per_token_idr' => 10_000,
            'tokens_offered' => 10_000,
            'start_date' => $this->simClock->now()->toDateTimeString(),
            'end_date' => $this->simClock->now()->addDays(14)->toDateTimeString(),
        ], idempotencyKey: 'sim:offering:001');

        // ==========================================
        // DAY 2: Oversubscribed Offering Subscriptions
        // ==========================================
        $this->simClock->advanceDays(1);
        $demands = [3_000, 4_000, 2_000, 2_500, 1_500]; // Total 13,000 > 10,000 supply
        foreach ($this->investors as $idx => $inv) {
            $rwaOfferingService->placeOrder($offering->id, $inv->id, $demands[$idx], "sim:order:{$idx}");
        }

        // ==========================================
        // DAY 15: Offering Close & Hamilton Allocation
        // ==========================================
        $this->simClock->advanceDays(13);
        $rwaOfferingService->closeAndAllocate($offering->id, idempotencyKey: 'sim:off:close');
        $rwaOfferingService->settleOffering($offering->id, idempotencyKey: 'sim:off:settle');

        expect(RwaHolding::where('rwa_asset_id', $issuedAsset->id)->sum('token_balance'))->toBe(10_000);

        // ==========================================
        // DAY 16: Secondary Market, Insurance & Wealth Setup
        // ==========================================
        $this->simClock->advanceDays(1);

        // Secondary trading: Investor 0 sells 100 tokens to Investor 1 at 12,000 IDR
        $buy = $rwaOrderbookService->placeLimitOrder([
            'rwa_asset_id' => $issuedAsset->id,
            'user_id' => $this->investors[1]->id,
            'side' => 'BUY',
            'quantity' => 100,
            'price_per_token_idr' => 12_000,
        ], 'sim:sec:buy:001');

        $sell = $rwaOrderbookService->placeLimitOrder([
            'rwa_asset_id' => $issuedAsset->id,
            'user_id' => $this->investors[0]->id,
            'side' => 'SELL',
            'quantity' => 100,
            'price_per_token_idr' => 12_000,
        ], 'sim:sec:sell:001');

        $tradeResult = $rwaOrderbookService->matchOrders($buy->id, $sell->id, idempotencyKey: 'sim:sec:match:001');
        expect($tradeResult['matched_quantity'])->toBe(100);

        // Insurance: Register BMKG oracle and issue policies
        $oracleService = app(ParametricOracleService::class);
        $oracle = $oracleService->registerOracle([
            'oracle_code' => 'BMKG_WEATHER_180',
            'name' => 'BMKG Weather 180',
            'source_type' => 'BMKG_WEATHER',
            'api_endpoint_config' => ['station_id' => 'HOTEL_180'],
        ], idempotencyKey: 'sim:oracle:bmkg');

        $product = InsuranceProduct::create([
            'product_code' => 'HOTEL_BIZ_INTERRUPT',
            'name' => 'Business Interruption Cover',
            'trigger_event_type' => 'WEATHER_DISRUPTION',
            'premium_amount_idr' => 60_000,
            'max_payout_idr' => 600_000,
            'is_embedded' => false,
        ]);

        /** @var list<InsurancePolicy> $policies */
        $policies = [];
        for ($i = 0; $i < 3; $i++) {
            $policies[] = $policyService->issuePolicy([
                'product_id' => $product->id,
                'user_id' => $this->investors[$i]->id,
                'subject_ref_type' => 'hotel_booking',
                'subject_ref_id' => 'BKG-'.$i,
                'duration_days' => 60,
            ], idempotencyKey: "sim:ins:iss:{$i}");
        }

        // Wealth: Profiling and Cash Sweep Configuration
        $profiles = [];
        foreach ($this->investors as $i => $inv) {
            $score = ($i % 3 === 0) ? 2 : (($i % 3 === 1) ? 3 : 5);
            $profiles[] = $roboAdvisorService->submitRiskSurvey($inv->id, ['q1' => $score, 'q2' => $score], "sim:wm:surv:{$i}");
            $sweepService->configureSweep([
                'user_id' => $inv->id,
                'threshold_cash_idr' => 50_000_000,
                'sweep_percentage_bps' => 5_000,
                'target_asset_code' => 'PU_INDEX',
                'is_active' => true,
            ], "sim:sweep:cfg:{$i}");
        }

        $portfolioService->recordNavUpdate('PU_INDEX', 10_000, 'INDEX_FEED', 'sim:nav:init');

        // ==========================================
        // DAYS 17 to 180: Daily Continuous Loop
        // ==========================================
        for ($day = 17; $day <= 180; $day++) {
            $this->simClock->advanceDays(1);

            // Daily insurance premium earnings recognition
            foreach ($policies as $pol) {
                if ($pol->refresh()->status === 'active' && ($day - 16) <= 60) {
                    $policyService->recognizeDailyEarning($pol->id, $day - 16, "sim:earn:d{$day}:p{$pol->id}");
                }
            }

            // Monthly dividend declarations (Day 30, 60, 90, 120, 150, 180)
            if ($day % 30 === 0) {
                $period = '2026-M0'.($day / 30);
                $this->creditRentalPool($issuedAsset, 5_000_000, "sim:rent:d{$day}");
                $rwaDividendService->declareDividend($issuedAsset->id, $period, 5_000_000, "sim:div:dec:d{$day}");
                $rwaDividendService->distributeDividend($issuedAsset->id, $period, "sim:div:dist:d{$day}");
            }

            // Day 45: Parametric Event (BMKG Rain / Flood) -> Claim Triggered and Auto-Approved (STP)
            if ($day === 45) {
                $claim = $claimsService->triggerParametricClaim(
                    oracleCode: $oracle->oracle_code,
                    policyId: $policies[0]->id,
                    triggerEventId: 'EVT-WEATHER-045',
                    evidencePayload: ['rainfall_mm' => 150, 'verified_carrier' => true],
                    idempotencyKey: 'sim:claim:d45'
                );
                expect($claim->status)->toBe('approved');
                $claimsService->settleApprovedClaim($claim->id, idempotencyKey: 'sim:claim:settle:d45');
            }

            // Day 60: Payroll Sweep & Rebalance
            if ($day === 60) {
                foreach ($this->investors as $i => $inv) {
                    $this->depositCash($inv, 10_000_000, "sim:pay:d60:{$i}");
                    $sweepService->handlePayrollDisbursedEvent(['user_id' => $inv->id, 'amount_idr' => 10_000_000], "sim:sweep:d60:{$i}");
                }
                $portfolioService->recordNavUpdate('PU_INDEX', 11_000, 'INDEX_FEED', "sim:nav:d{$day}");
                foreach ($profiles as $prof) {
                    $portfolioService->revaluePortfolioMtm($prof->id, "sim:mtm:d{$day}:{$prof->id}");
                }
            }

            // Day 77: Policy expiration check
            if ($day === 77) {
                foreach ($policies as $pol) {
                    if ($pol->refresh()->status === 'active') {
                        $policyService->expireLapsedPolicy($pol->id, "sim:exp:d{$day}:{$pol->id}");
                    }
                }
            }

            // Day 90: Management fee charge
            if ($day === 90) {
                foreach ($profiles as $prof) {
                    $portfolioService->chargeMonthlyManagementFee($prof->id, 100, "sim:fee:d90:{$prof->id}");
                }
            }
        }

        // ==========================================
        // VERIFICATIONS AFTER 180 DAYS
        // ==========================================
        // 1. Two-source audits for all three modules
        $rwaExit = Artisan::call('rwa:audit');
        expect($rwaExit)->toBe(0);

        $insExit = Artisan::call('ins:audit');
        expect($insExit)->toBe(0);

        $wmExit = Artisan::call('wm:audit');
        expect($wmExit)->toBe(0);

        // 2. Token conservation invariant: Total tokens in circulation must equal 10,000
        $totalTokens = (int) RwaHolding::where('rwa_asset_id', $issuedAsset->id)->sum('token_balance');
        expect($totalTokens)->toBe(10_000);

        // 3. Dividend zero-dust conservation invariant
        $totalDividendsDeclared = (int) RwaDividend::where('rwa_asset_id', $issuedAsset->id)->sum('total_revenue_pool_idr');
        $totalDividendsPaid = (int) RwaDividend::where('rwa_asset_id', $issuedAsset->id)->sum('distributed_idr');
        expect($totalDividendsDeclared)->toBe(6 * 5_000_000)
            ->and($totalDividendsPaid)->toBe(30_000_000);

        // 4. Deterministic balance hash check
        $hashData = DB::table('bank_ledger_accounts')->orderBy('code')->pluck('cached_balance', 'code')->toJson();
        $stateHash = hash('sha256', $hashData);
        expect($stateHash)->toBe('1d7f1dfb41f77280a8c67caf02c930d278d9cd49c658e99b8f6eea911b6d528a');
    }

    protected function depositCash(User $user, int $amount, string $key): void
    {
        $wallet = $user->walletAccount('IDR');

        $this->ledgerService->post(new PostingDTO(
            type: 'topup',
            description: "Cash deposit for user {$user->id}",
            idempotencyKey: $key,
            entries: [
                new PostingEntryDTO(
                    accountId: null,
                    accountCode: 'clearing:external:IDR',
                    assetCode: 'IDR',
                    amount: -$amount
                ),
                new PostingEntryDTO(
                    accountId: null,
                    accountCode: $wallet->code,
                    assetCode: 'IDR',
                    amount: $amount
                ),
            ]
        ));
    }

    protected function creditRentalPool(RwaAsset $asset, int $amount, string $key): void
    {
        $pool = RwaAccounts::revenuePool($asset->token_symbol);

        $this->ledgerService->post(new PostingDTO(
            type: 'topup',
            description: "Rental pool credit for asset {$asset->id}",
            idempotencyKey: $key,
            entries: [
                new PostingEntryDTO(
                    accountId: null,
                    accountCode: 'clearing:external:IDR',
                    assetCode: 'IDR',
                    amount: -$amount
                ),
                new PostingEntryDTO(
                    accountId: null,
                    accountCode: $pool->code,
                    assetCode: 'IDR',
                    amount: $amount
                ),
            ]
        ));
    }
}
