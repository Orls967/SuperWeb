<?php

declare(strict_types=1);

namespace Tests\Acceptance\LintasLini3;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Edu\Application\Actions\IssueVerifiedCompetencyCertificateAction;
use Modules\Edu\Contracts\CredentialVerifier;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduProgram;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Intercompany\Application\Services\IntercompanyService;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Manufacturing\Application\Actions\IssueMaintenanceLotoSafetyPermitAction;
use Modules\Manufacturing\Domain\Exceptions\SafetyPermitUnauthorizedException;
use Modules\Manufacturing\Domain\Models\Plant;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Modules\Med\Application\Services\AdImpressionService;
use Modules\Med\Domain\Models\MedAdPlacement;
use Modules\Med\Domain\Models\MedDistributionChannel;
use Modules\Ret\Application\Actions\CheckoutOmnichannelOrderAction;
use Modules\Ret\Application\Actions\EarnSuperAppCashbackAction;
use Modules\Ret\Domain\Models\RetCashbackTransaction;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetSeller;
use Modules\Tlx\Application\Services\ConnectivityBillingService;
use Modules\Tlx\Contracts\IotDeviceRegistry;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Tlx\Domain\Models\SimProfile;
use Tests\TestCase;

class MultiLineE2EIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * Skenario Terpadu Ekosistem Lima Lini Bisnis & Konsolidasi Grup:
     * 1. Satu entitas grup menerima tagihan energi (Egy) dan konektivitas (Tlx) lewat Intercompany.
     * 2. Satu operator pabrik mengikuti pelatihan K3 (Edu) lalu menerbitkan izin LOTO di Manufacturing.
     * 3. Satu studio media (Med) mengiklankan produk seller Retail (Ret).
     * 4. Satu customer membeli produk ritel tersebut dengan cashback reward.
     * 5. Konsolidasi grup tereliminasi; buku besar global seimbang (Σ = 0 per aset).
     * 6. Semua command audit lini (egy, ret, edu, tlx, med) + group:audit + chain:audit-all + bank:reconcile exit 0.
     */
    public function test_multiline_e2e_full_ecosystem_joint_simulation_scenario(): void
    {
        $groupEntityId = 'ENT-CONGLOMERATE-HOLDING';

        // ════════════════════════════════════════════════════════════════════════
        // 1. EGY & TLX INTERCOMPANY BILLING TO GROUP HOLDING
        // ════════════════════════════════════════════════════════════════════════

        // 1.a. Energy PPA Settlement (Producer to Holding Entity)
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-E2E-SOLAR-01',
            'producer_entity_id' => 'ENT-EGY-SOLAR-FARM',
            'grid_offtaker_id' => $groupEntityId,
            'feed_in_tariff_per_kwh_minor' => 1500, // Rp 1,500 / kWh
            'status' => 'ACTIVE',
        ]);

        $ppaAction = app(SettlePowerPurchaseAgreementBillingAction::class);
        $ppaResult = $ppaAction->executeWithIntercompany(
            (string) $ppa->id,
            5000, // 5,000 kWh -> Rp 7,500,000 IDR
            'e2e:ic:ppa:solar:202610'
        );

        $this->assertEquals('SETTLED', $ppaResult['status']);
        $this->assertEquals(7_500_000, $ppaResult['total_minor']);

        $ppaIc = IntercompanyTransaction::find($ppaResult['mirrored_pair_id']);
        $this->assertNotNull($ppaIc);
        $this->assertEquals('ENT-EGY-SOLAR-FARM', $ppaIc->selling_entity);
        $this->assertEquals($groupEntityId, $ppaIc->buying_entity);
        $this->assertEquals(7_500_000, (int) $ppaIc->amount_idr);

        // 1.b. Telecom Connectivity Billing (IoT SIM Fleets to Holding Entity)
        $iotRegistry = app(IotDeviceRegistry::class);
        $iotRegistry->registerDevice([
            'device_code' => 'DEV-E2E-TELEMATICS-01',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => $groupEntityId,
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 75_000, // Rp 75,000 / month
            'idempotency_key' => 'TLX-REG:DEV-E2E-TELEMATICS-01',
        ]);

        SimProfile::create([
            'iccid' => '8962000000008888',
            'imsi' => '510100000008888',
            'msisdn' => '628990008888',
            'plan_code' => 'IOT-5GB',
            'monthly_quota_mb' => 5000,
            'status' => 'AVAILABLE',
        ]);
        $iotRegistry->activateSim('DEV-E2E-TELEMATICS-01', '8962000000008888', 'TLX-ACT:DEV-E2E-TELEMATICS-01');

        $tlxBilling = app(ConnectivityBillingService::class);
        $tlxInvoice = $tlxBilling->billEntityConnectivity([
            'owner_entity_id' => $groupEntityId,
            'billing_period' => '2026-10',
            'days_in_month' => 31,
            'idempotency_key' => 'TLX-BILL:ENT-CONGLOMERATE:2026-10',
        ]);

        $this->assertInstanceOf(IotConnectivityInvoice::class, $tlxInvoice);
        $this->assertEquals('ISSUED', $tlxInvoice->status);
        $this->assertEquals(75_000, $tlxInvoice->total_charge_minor);
        $this->assertNotNull($tlxInvoice->intercompany_transaction_id);

        $tlxIc = IntercompanyTransaction::find($tlxInvoice->intercompany_transaction_id);
        $this->assertNotNull($tlxIc);
        $this->assertEquals('TLX', $tlxIc->selling_entity);
        $this->assertEquals($groupEntityId, $tlxIc->buying_entity);
        $this->assertEquals(75_000, (int) $tlxIc->amount_idr);

        // ════════════════════════════════════════════════════════════════════════
        // 2. EDU K3 TRAINING & MANUFACTURING LOTO PERMIT
        // ════════════════════════════════════════════════════════════════════════

        $operator = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@mfg.group.com',
            'role' => 'operator',
        ]);

        $eduProgram = EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => 'k3_loto',
            'title' => 'Sertifikasi Keselamatan Kerja LOTO Kelistrikan Industri',
            'industry_sector' => 'MANUFACTURING',
            'tuition_fee_minor' => 5_000_000,
            'passing_score' => 80,
            'validity_months' => 24,
            'status' => 'ACTIVE',
        ]);

        $eduCohort = EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-K3-2026-01',
            'program_id' => $eduProgram->id,
            'instructor_party_id' => 'instructor-k3-01',
            'max_capacity' => 25,
            'enrolled_count' => 1,
            'start_date' => now()->subMonths(1)->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $enrollment = EduEnrollment::create([
            'id' => (string) Str::uuid(),
            'enrollment_code' => 'ENR-K3-BUDI-01',
            'student_party_id' => (string) $operator->id,
            'student_user_id' => $operator->id,
            'cohort_id' => $eduCohort->id,
            'amount_paid_minor' => 5_000_000,
            'sessions_attended' => 10,
            'total_sessions' => 10,
            'status' => 'ENROLLED',
        ]);

        $examAttempt = EduExamAttempt::create([
            'id' => (string) Str::uuid(),
            'attempt_code' => 'EXAM-K3-BUDI-01',
            'student_party_uuid' => (string) $operator->id,
            'student_user_id' => $operator->id,
            'enrollment_id' => $enrollment->id,
            'exam_title' => 'Ujian Sertifikasi Kompetensi K3 LOTO 380V',
            'score' => 95,
            'passing_score' => 80,
            'is_passed' => true,
            'proctor_verification_hash' => hash('sha256', "proctor:k3:{$operator->id}"),
            'status' => 'COMPLETED',
        ]);

        $issueCertAction = app(IssueVerifiedCompetencyCertificateAction::class);
        $certificate = $issueCertAction->execute([
            'enrollment_id' => $enrollment->id,
            'exam_attempt_id' => $examAttempt->id,
            'program_id' => $eduProgram->id,
            'skill_competency_code' => 'k3_loto',
            'idempotency_key' => 'edu:cert:issue:k3:budi-01',
        ]);

        $this->assertInstanceOf(EduCertificate::class, $certificate);
        $this->assertEquals('ACTIVE', $certificate->status);
        $this->assertEquals('k3_loto', $certificate->skill_competency_code);

        // Verify CredentialVerifier port confirms valid certificate
        $verifier = app(CredentialVerifier::class);
        $this->assertTrue($verifier->hasValid($operator->id, 'k3_loto', CarbonImmutable::now()));

        // Issue Manufacturing LOTO Safety Permit using verified certificate
        $plant = Plant::create([
            'code' => 'PLANT-CIKARANG-01',
            'name' => 'Cikarang Industrial Assembly Plant',
            'location' => 'Kawasan GIIC Cikarang',
            'is_active' => true,
        ]);

        $workCenter = WorkCenter::create([
            'plant_id' => $plant->id,
            'code' => 'WC-SMT-HV-01',
            'name' => 'High Voltage SMT Feed & Assembly Station',
            'capacity_units_per_hour' => 120,
            'is_active' => true,
        ]);

        $lotoAction = app(IssueMaintenanceLotoSafetyPermitAction::class);
        $permit = $lotoAction->execute(
            workCenter: $workCenter,
            permitType: 'loto',
            hazards: '380V Industrial Voltage & Pressurized Pneumatics',
            controls: 'Dual master padlock LOTO applied, main breaker isolated, pneumatic line bled',
            validFrom: now(),
            validUntil: now()->addHours(8),
            requestedByUserId: $operator->id,
        );

        $this->assertNotNull($permit);
        $this->assertEquals('active', $permit->status);
        $this->assertEquals($operator->id, $permit->requested_by_user_id);

        // ════════════════════════════════════════════════════════════════════════
        // 3. MED STUDIO ADVERTISING RETAIL SELLER PRODUCT
        // ════════════════════════════════════════════════════════════════════════

        $retChannel = RetChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'ONLINE_MALL',
            'channel_name' => 'Conglomerate Online Store',
            'channel_type' => 'WEB',
            'default_commission_bps' => 500, // 5%
            'status' => 'ACTIVE',
        ]);

        $seller = RetSeller::create([
            'id' => (string) Str::uuid(),
            'party_id' => 'party-seller-tech-01',
            'seller_name' => 'OmniTech Smart Devices Store',
            'kyb_status' => 'VERIFIED',
            'commission_override_bps' => 500,
            'payout_account_ref' => 'payout-omnitech-01',
        ]);

        $medChannel = MedDistributionChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'CH-MALL-OOH',
            'name' => 'Mall Atrium Digital OOH Screen Network',
            'channel_type' => 'OOH_SCREEN',
            'revenue_share_pct' => 70.0,
            'status' => 'ACTIVE',
        ]);

        $placement = MedAdPlacement::create([
            'id' => (string) Str::uuid(),
            'channel_id' => $medChannel->id,
            'placement_code' => 'PLC-MAIN-ATRIUM-01',
            'location_name' => 'Grand Atrium Main Display Screen',
            'floor_cpm_minor' => 60_000,
            'is_active' => true,
        ]);

        $adService = app(AdImpressionService::class);
        $campaign = $adService->bookCampaign([
            'placement_id' => $placement->id,
            'advertiser_entity_id' => (string) $seller->id,
            'target_impressions' => 2000,
            'actual_cpm_minor' => 75_000, // Above floor 60,000
            'idempotency_key' => 'med:ad:campaign:omnitech:202610',
        ]);

        $this->assertEquals('SCHEDULED', $campaign->status);

        // Ingest verified impressions
        for ($i = 1; $i <= 5; $i++) {
            $adService->ingestImpression([
                'campaign_id' => $campaign->id,
                'impression_hash' => hash('sha256', "e2e:imp:omnitech:{$i}"),
                'device_ip_hash' => hash('sha256', "ooh:disp:{$i}"),
                'timestamp_utc' => CarbonImmutable::now()->subMinutes(5 * $i)->toIso8601String(),
            ]);
        }

        // Settle Campaign
        $settledCampaign = $adService->settleCampaign($campaign->id);
        $this->assertEquals('COMPLETED', $settledCampaign->status);
        $this->assertEquals(5, $settledCampaign->verified_impressions);

        // ════════════════════════════════════════════════════════════════════════
        // 4. CUSTOMER PURCHASES PRODUCT WITH SUPERAPP CASHBACK (RET)
        // ════════════════════════════════════════════════════════════════════════

        $customer = User::factory()->create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti.customer@domain.com',
            'role' => 'customer',
        ]);

        $checkoutAction = app(CheckoutOmnichannelOrderAction::class);
        $order = $checkoutAction->execute([
            'channel_id' => $retChannel->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'items' => [
                ['sku' => 'SKU-SMART-OVEN-01', 'quantity' => 1, 'unit_price_minor' => 2_500_000],
            ],
            'packaging_deposit_minor' => 25_000,
            'idempotency_key' => 'ret:order:siti-oven-01',
        ]);

        $this->assertInstanceOf(RetOrder::class, $order);
        $this->assertEquals('PAID', $order->status);
        $this->assertEquals(2_525_000, $order->total_amount_minor);

        $cashbackAction = app(EarnSuperAppCashbackAction::class);
        $cashback = $cashbackAction->execute([
            'customer_id' => $customer->id,
            'amount_minor' => 50_000, // Rp 50,000 cashback
            'source_domain' => 'marketplace',
            'reference_id' => $order->order_number,
            'idempotency_key' => 'ret:cashback:siti-oven-01',
        ]);

        $this->assertInstanceOf(RetCashbackTransaction::class, $cashback);
        $this->assertEquals('EARNED', $cashback->type);
        $this->assertEquals(50_000, $cashback->amount_minor);

        // ════════════════════════════════════════════════════════════════════════
        // 5. GROUP ELIMINATION & CONSOLIDATION VERIFICATION
        // ════════════════════════════════════════════════════════════════════════

        $icService = app(IntercompanyService::class);

        // Eliminate Energy PPA reciprocal AR / AP
        $icService->postEliminationEntry(
            period: '2026-10',
            eliminationType: 'RECIPROCAL_AP_AR',
            debitAccount: 'ap:ic:egy:producer:IDR',
            creditAccount: 'ar:ic:producer:egy:IDR',
            amountIdr: 7_500_000
        );

        // Eliminate Telecom Connectivity reciprocal AR / AP
        $icService->postEliminationEntry(
            period: '2026-10',
            eliminationType: 'RECIPROCAL_AP_AR',
            debitAccount: 'tlx:intercompany_payable:IDR',
            creditAccount: 'tlx:intercompany_receivable:IDR',
            amountIdr: 75_000
        );

        $eliminations = EliminationEntry::where('period', '2026-10')->get();
        $this->assertCount(2, $eliminations);
        $this->assertEquals(7_575_000, $eliminations->sum('amount_idr'));

        // ════════════════════════════════════════════════════════════════════════
        // 6. GLOBAL DOUBLE-ENTRY LEDGER BALANCE & AUDIT SYSTEM GATES
        // ════════════════════════════════════════════════════════════════════════

        // Verify Global General Ledger: sum of entries per currency must be exactly 0
        $balances = LedgerEntry::selectRaw('asset_code, SUM(amount) as net_balance')
            ->groupBy('asset_code')
            ->get();

        $this->assertNotEmpty($balances);
        foreach ($balances as $b) {
            $this->assertEquals(0, (int) $b->net_balance, "Ledger asset [{$b->asset_code}] net balance is not zero!");
        }

        // Verify All Five Business Line Audits Exit 0 With Zero Discrepancies
        $this->assertSame(0, Artisan::call('egy:audit'), 'egy:audit failed');
        $this->assertSame(0, Artisan::call('ret:audit'), 'ret:audit failed');
        $this->assertSame(0, Artisan::call('edu:audit'), 'edu:audit failed');
        $this->assertSame(0, Artisan::call('tlx:audit'), 'tlx:audit failed');
        $this->assertSame(0, Artisan::call('med:audit'), 'med:audit failed');

        // Verify Group Consolidation, Global Chain, and Bank Reconciliation Exit 0
        $this->assertSame(0, Artisan::call('group:audit'), 'group:audit failed');
        $this->assertSame(0, Artisan::call('chain:audit-all'), 'chain:audit-all failed');
        $this->assertSame(0, Artisan::call('bank:reconcile'), 'bank:reconcile failed');
    }

    /**
     * Negative Boundary: Operator without active K3 LOTO certification cannot obtain LOTO safety permit.
     */
    public function test_manufacturing_loto_permit_rejected_without_valid_edu_certificate(): void
    {
        $unqualifiedOperator = User::factory()->create([
            'name' => 'Untrained Operator',
            'role' => 'operator',
        ]);

        $plant = Plant::create([
            'code' => 'PLANT-UNQ-01',
            'name' => 'Untrained Plant',
            'location' => 'Cikarang',
            'is_active' => true,
        ]);

        $workCenter = WorkCenter::create([
            'plant_id' => $plant->id,
            'code' => 'WC-UNQ-01',
            'name' => 'High Voltage Uncertified Station',
            'capacity_units_per_hour' => 50,
            'is_active' => true,
        ]);

        $lotoAction = app(IssueMaintenanceLotoSafetyPermitAction::class);

        $this->expectException(SafetyPermitUnauthorizedException::class);
        $this->expectExceptionMessage('lacks active k3_loto');

        $lotoAction->execute(
            workCenter: $workCenter,
            permitType: 'loto',
            hazards: '380V High Voltage Feed',
            controls: 'Single lock',
            validFrom: now(),
            validUntil: now()->addHours(4),
            requestedByUserId: $unqualifiedOperator->id,
        );
    }

    /**
     * Verifikasi integritas eliminasi konsolidasi resiprokal intercompany antar entitas grup.
     */
    public function test_intercompany_elimination_symmetry_and_group_audit(): void
    {
        $icService = app(IntercompanyService::class);

        // Record a symmetrical intercompany loan & elimination
        $loan = $icService->issueIntercompanyLoan(
            lender: 'ENT-CONGLOMERATE-HOLDING',
            borrower: 'ENT-EGY-SOLAR-FARM',
            principalIdr: 50_000_000,
            armsLengthRate: 6.5,
            dueDate: CarbonImmutable::now()->addYear()->toDateString()
        );

        $this->assertEquals('active', $loan->status);
        $this->assertEquals(50_000_000, $loan->principal_idr);

        $elimination = $icService->postEliminationEntry(
            period: '2026-10',
            eliminationType: 'INTERCOMPANY_LOAN_RECIPROCAL',
            debitAccount: 'ap:ic:loan:egy:IDR',
            creditAccount: 'ar:ic:loan:holding:IDR',
            amountIdr: 50_000_000
        );

        $this->assertEquals('posted', $elimination->status);
        $this->assertEquals(50_000_000, $elimination->amount_idr);

        // Group consolidation audit must pass with 0 discrepancy
        $auditRes = $icService->auditIntercompany();
        $this->assertEquals('OK', $auditRes['status']);
        $this->assertEquals(0, $auditRes['discrepancy_count']);
        $this->assertSame(0, Artisan::call('group:audit'));
    }
}
