<?php

declare(strict_types=1);

namespace Tests\Acceptance\SybOperations;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Syariah\Application\Services\HalalTransactionFilterService;
use Modules\Syariah\Application\Services\NpfClassificationService;
use Modules\Syariah\Application\Services\ShariahLedgerPoster;
use Modules\Syariah\Application\Services\SyariahOperationsCommandCenterService;
use Modules\Syariah\Application\Services\SyariahRecoveryWaterfallService;
use Modules\Syariah\Application\Services\SyariahRegulatoryReportService;
use Modules\Syariah\Application\Services\SyariahRestructuringService;
use Modules\Syariah\Application\Services\UniversalSyariahWalletService;
use Modules\Syariah\Domain\Models\SybHalalCategory;
use Modules\Syariah\Domain\Models\SybHalalMerchant;
use Modules\Syariah\Ledger\SyariahAccounts;
use Tests\TestCase;

final class SybOperationsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private SyariahRestructuringService $restructuringService;

    private NpfClassificationService $npfService;

    private UniversalSyariahWalletService $walletService;

    private SyariahOperationsCommandCenterService $commandCenter;

    protected function setUp(): void
    {
        parent::setUp();

        SyariahAccounts::ensureAccountsExist();

        $ledgerPoster = app(ShariahLedgerPoster::class);
        $filterService = app(HalalTransactionFilterService::class);

        $this->restructuringService = new SyariahRestructuringService($ledgerPoster);
        $this->npfService = new NpfClassificationService($ledgerPoster);
        $this->walletService = new UniversalSyariahWalletService($filterService, $ledgerPoster);
        $this->commandCenter = app(SyariahOperationsCommandCenterService::class);
    }

    public function test_restructuring_schedule_rescheduling_without_illegal_margin_addition(): void
    {
        // 1. Pengajuan restrukturisasi
        $req = $this->restructuringService->requestRestructuring(
            financingRef: 'FIN-MBR-001',
            customerRef: 'CUST-001',
            contractType: 'MURABAHAH',
            originalOutstandingPrincipalMinor: 120_000_000,
            originalRemainingMarginMinor: 24_000_000,
            marginHaircutBps: 5000, // Haircut 50% -> revised margin 12.000.000
            originalTenorMonths: 12,
            newTenorMonths: 14,
            gracePeriodMonths: 2,
            reason: 'Terdampak penurunan omzet usaha kuliner',
            requestedBy: 'OFFICER-01'
        );

        $this->assertSame(120_000_000, $req->revised_principal_minor);
        $this->assertSame(12_000_000, $req->revised_margin_minor);
        $this->assertSame('REQUESTED', $req->status);

        // 2. Reviu DPS
        $req = $this->restructuringService->reviewByDps(
            requestRef: $req->request_ref,
            dpsReviewerRef: 'DPS-USTADZ-AHMAD',
            notes: 'Restrukturisasi sesuai syariah tanpa pertambahan margin di atas akad awal',
            verdict: 'DPS_REVIEWED'
        );
        $this->assertSame('DPS_REVIEWED', $req->status);

        // 3. Approval & Eksekusi
        $req = $this->restructuringService->approve($req->request_ref);
        $req = $this->restructuringService->execute($req->request_ref);
        $this->assertSame('EXECUTED', $req->status);

        // Verifikasi jadwal cicilan
        $this->assertDatabaseCount('syb_restructured_installments', 14);

        // Bulan 1 adalah grace period
        $graceInstallment = $req->installments()->where('month_number', 1)->first();
        $this->assertTrue($graceInstallment->is_grace);
        $this->assertSame('WAIVED', $graceInstallment->payment_status);

        // Bulan 3 adalah cicilan aktif
        $activeInstallment = $req->installments()->where('month_number', 3)->first();
        $this->assertFalse($activeInstallment->is_grace);
        $this->assertSame('PENDING', $activeInstallment->payment_status);

        // 4. Pembayaran cicilan bulan 3
        $paid = $this->restructuringService->payInstallment($req->request_ref, 3);
        $this->assertSame('PAID', $paid->payment_status);
        $this->assertNotNull($paid->ledger_journal_ref);
    }

    public function test_npf_classification_and_automatic_provision_recognition(): void
    {
        $contracts = [
            ['contract_ref' => 'FIN-K1', 'customer_ref' => 'C1', 'product_type' => 'MURABAHAH', 'dpd' => 0, 'outstanding_principal_minor' => 800_000_000, 'remaining_margin_minor' => 80_000_000],
            ['contract_ref' => 'FIN-K2', 'customer_ref' => 'C2', 'product_type' => 'IJARAH', 'dpd' => 30, 'outstanding_principal_minor' => 100_000_000, 'remaining_margin_minor' => 10_000_000],
            ['contract_ref' => 'FIN-K3', 'customer_ref' => 'C3', 'product_type' => 'MICRO', 'dpd' => 95, 'outstanding_principal_minor' => 50_000_000, 'remaining_margin_minor' => 5_000_000],
            ['contract_ref' => 'FIN-K4', 'customer_ref' => 'C4', 'product_type' => 'SCF', 'dpd' => 140, 'outstanding_principal_minor' => 30_000_000, 'remaining_margin_minor' => 3_000_000],
            ['contract_ref' => 'FIN-K5', 'customer_ref' => 'C5', 'product_type' => 'AGRI', 'dpd' => 200, 'outstanding_principal_minor' => 20_000_000, 'remaining_margin_minor' => 2_000_000],
        ];

        $classification = $this->npfService->evaluateAndClassify(
            periodLabel: '2026-11',
            contractInputs: $contracts,
            evaluatedBy: 'CHIEF_RISK_OFFICER'
        );

        $this->assertSame(1_000_000_000, $classification->total_portfolio_minor);
        $this->assertSame(900_000_000, $classification->performing_principal_minor);
        $this->assertSame(100_000_000, $classification->non_performing_principal_minor);
        $this->assertSame(1000, $classification->npf_gross_bps); // 10.00%
        $this->assertFalse($classification->is_compliant); // > 500 bps (5%)
        $this->assertNotEmpty($classification->certificate_hash);

        // Verifikasi snapshot terbuat
        $this->assertDatabaseCount('syb_npf_snapshots', 5);
    }

    public function test_universal_syariah_wallet_payment_and_idempotency_gate(): void
    {
        // Siapkan kategori dan merchant halal
        $category = SybHalalCategory::create([
            'category_code' => 'HALAL_FOOD',
            'name' => 'Restoran & Makanan Halal',
            'line_code' => 'RESTO',
            'is_halal_mandatory' => true,
            'is_strictly_prohibited' => false,
        ]);

        SybHalalMerchant::create([
            'merchant_code' => 'MCH-RESTO-001',
            'name' => 'Dapur Halal Nusantara',
            'category_code' => $category->category_code,
            'line_code' => 'RESTO',
            'certificate_number' => 'ID31110001234560124',
            'issuing_authority' => 'BPJPH',
            'valid_from' => Carbon::now()->subYear()->toDateString(),
            'expires_at' => Carbon::now()->addYear()->toDateString(),
            'status' => 'ACTIVE',
            'is_blacklisted' => false,
        ]);

        $orderRef = 'ORD-RST-998877';

        // Pembayaran pertama
        $result1 = $this->walletService->pay(
            customerAccountRef: 'WALLET-USER-001',
            merchantCode: 'MCH-RESTO-001',
            businessLineCode: 'RESTO',
            amountMinor: 250_000_00, // Rp 250.000
            orderReference: $orderRef,
            metadata: ['table' => 12, 'outlet' => 'DUTA_MALL']
        );

        $this->assertTrue($result1->success);
        $this->assertSame($orderRef, $result1->orderReference);
        $this->assertTrue($result1->isHalalVerified);
        $this->assertSame(250_000_00, $result1->grossAmountMinor);
        $this->assertNotNull($result1->ledgerJournalRef);

        // Pembayaran kedua dengan orderReference yang sama (Idempotency)
        $result2 = $this->walletService->pay(
            customerAccountRef: 'WALLET-USER-001',
            merchantCode: 'MCH-RESTO-001',
            businessLineCode: 'RESTO',
            amountMinor: 250_000_00,
            orderReference: $orderRef
        );

        $this->assertSame($result1->transactionRef, $result2->transactionRef);
        $this->assertDatabaseCount('syb_universal_wallet_transactions', 1);
    }

    public function test_halal_filter_strictly_blocks_unauthorized_and_prohibited_merchants(): void
    {
        // Kategori haram: Judi / Taruhan
        $haramCategory = SybHalalCategory::create([
            'category_code' => 'GAMBLING',
            'name' => 'Perjudian & Taruhan',
            'line_code' => 'ENTERTAINMENT',
            'is_halal_mandatory' => true,
            'is_strictly_prohibited' => true,
        ]);

        SybHalalMerchant::create([
            'merchant_code' => 'MCH-CASINO-001',
            'name' => 'Royal Betting Club',
            'category_code' => $haramCategory->category_code,
            'line_code' => 'ENTERTAINMENT',
            'certificate_number' => null,
            'status' => 'ACTIVE',
            'is_blacklisted' => true,
            'blacklist_reason' => 'Perjudian dilarang mutlak dalam syariat Islam',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is blacklisted');

        $this->walletService->pay(
            customerAccountRef: 'WALLET-USER-002',
            merchantCode: 'MCH-CASINO-001',
            businessLineCode: 'ENTERTAINMENT',
            amountMinor: 1_000_000_00,
            orderReference: 'ORD-ILLEGAL-001'
        );
    }

    public function test_operations_command_center_and_dps_incident_governance(): void
    {
        // 1. Catat insiden operasional
        $incident = $this->commandCenter->recordIncident(
            severity: 'HIGH',
            category: 'SUSPICIOUS_TRANSACTION',
            description: 'Upaya transaksi ganda pada merchant belum tersertifikasi',
            entityReference: 'MCH-ALERT-001'
        );
        $this->assertSame('OPEN', $incident->resolution_status);

        // Resolusi insiden
        $resolved = $this->commandCenter->resolveIncident(
            incidentRef: $incident->incident_ref,
            notes: 'Merchant telah dikonfirmasi dan diblokir sementara'
        );
        $this->assertSame('RESOLVED', $resolved->resolution_status);

        // 2. Reviu DPS
        $dpsReview = $this->commandCenter->recordDpsReview(
            periodLabel: '2026-Q4',
            reviewType: 'ANNUAL_AUDIT',
            reviewerName: 'Prof. Dr. KH. Didin Hafidhuddin',
            verdict: 'COMPLIANT',
            findings: 'Seluruh operasional perbankan syariah telah beroperasi sesuai fatwa DSN-MUI.',
            recommendations: 'Tingkatkan literasi zakat dan pembiayaan mikro syariah.'
        );
        $this->assertSame('COMPLIANT', $dpsReview->verdict);

        // 3. Audit Operasi Bersih
        $audit = $this->commandCenter->auditOperations();
        $this->assertTrue($audit['is_clean']);
        $this->assertEmpty($audit['findings']);

        // 4. Laporan Regulator LBBS & Pemulihan Lelang Agunan
        $reportService = app(SyariahRegulatoryReportService::class);
        $lbbs = $reportService->generateLbbsReport('2026-11');
        $this->assertArrayHasKey('portfolio_summary', $lbbs);
        $this->assertNotEmpty($lbbs['report_hash']);

        $recoveryService = app(SyariahRecoveryWaterfallService::class);
        $auction = $recoveryService->scheduleCollateralAuction(
            financingRef: 'FIN-MACET-001',
            collateralDescription: 'Tanah Ruko Pasar Halal',
            appraisedValueMinor: 500_000_000,
            reservePriceMinor: 400_000_000,
            auctionDate: Carbon::now()->addDays(7)
        );

        $settled = $recoveryService->settleAuction(
            auctionRef: $auction->auction_ref,
            winningBidMinor: 450_000_000,
            buyerRef: 'PEMBELI-LELANG-01',
            outstandingDebtMinor: 420_000_000
        );

        $this->assertSame('COMPLETED', $settled->status);
        $this->assertSame(30_000_000, $settled->surplus_refunded_to_customer_minor); // Kelebihan Rp 30jt dikembalikan
    }
}
