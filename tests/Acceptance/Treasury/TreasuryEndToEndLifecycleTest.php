<?php

declare(strict_types=1);

namespace Tests\Acceptance\Treasury;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Treasury\Application\Actions\DrawFacilityAction;
use Modules\Treasury\Application\Actions\ExecuteCashPoolSweepAction;
use Modules\Treasury\Application\Actions\GenerateSeasonalCashForecastAction;
use Modules\Treasury\Application\Actions\ProcessPettyCashVoucherAction;
use Modules\Treasury\Application\Actions\ReconcileBankStatementsAction;
use Modules\Treasury\Application\Actions\ReverseRevaluationsAction;
use Modules\Treasury\Application\Actions\RunFxRevaluationAction;
use Modules\Treasury\Application\Actions\SettleForwardContractAction;
use Modules\Treasury\Application\Services\BankStatementImportService;
use Modules\Treasury\Application\Services\TreasuryCovenantGovernanceService;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Domain\Algorithms\RationalRate;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine;
use Tests\TestCase;

class TreasuryEndToEndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryService $service;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(TreasuryService::class);
        $this->ledgerService = app(LedgerService::class);
    }

    public function test_full_treasury_lifecycle_end_to_end_with_exact_ledger_postings_and_idempotency(): void
    {
        $treasuryUser = User::factory()->create(['name' => 'Dealer Treasury', 'role' => 'treasury']);
        $managerUser = User::factory()->create(['name' => 'Manager Treasury', 'role' => 'treasury']);

        // 1. Setup Master Mata Uang & Kurs Rasional
        $this->service->registerCurrency('USD', 'US Dollar', '$', 2);
        $this->service->registerCurrency('IDR', 'Indonesian Rupiah', 'Rp', 0);

        $this->service->recordExchangeRate(
            from: 'USD',
            to: 'IDR',
            rateDate: '2026-11-01',
            rateNumerator: 15500,
            rateDenominator: 1,
            rateType: 'spot'
        );

        $this->service->recordExchangeRate(
            from: 'USD',
            to: 'IDR',
            rateDate: '2026-11-30',
            rateNumerator: 15650,
            rateDenominator: 1,
            rateType: 'closing'
        );

        $this->service->recordExchangeRate(
            from: 'USD',
            to: 'IDR',
            rateDate: '2026-12-05',
            rateNumerator: 15700,
            rateDenominator: 1,
            rateType: 'spot'
        );

        // 2. Transaksi Multi-Currency & Subledger Lot Valas (Idempotency Key Enforcement)
        // Tanpa Idempotency-Key -> ditolak 422
        $responseNoKey = $this->actingAs($treasuryUser)->postJson('/api/treasury/fx-transfers', [
            'from_account' => 'TRS_USD_SRC',
            'to_account' => 'TRS_USD_DEST',
            'foreign_amount' => 100_000,
            'foreign_currency' => 'USD',
            'description' => 'Pembelian bahan baku impor',
        ]);
        $responseNoKey->assertStatus(422);

        // Dengan Idempotency-Key -> Berhasil
        $idemKey = 'IDEM:TRS:TEST_FX_001';
        $postPayload = [
            'from_account' => 'TRS_USD_SRC',
            'to_account' => 'TRS_USD_DEST',
            'foreign_amount' => 100_000,
            'foreign_currency' => 'USD',
            'description' => 'Pembelian bahan baku impor',
            'idempotency_key' => $idemKey,
        ];

        $postRes1 = $this->actingAs($treasuryUser)->withHeader('Idempotency-Key', $idemKey)
            ->postJson('/api/treasury/fx-transfers', $postPayload);
        $this->assertTrue(in_array($postRes1->status(), [200, 201], true));

        // Rerun dengan Idempotency-Key sama -> Satu efek (idempoten murni)
        $postRes2 = $this->actingAs($treasuryUser)->withHeader('Idempotency-Key', $idemKey)
            ->postJson('/api/treasury/fx-transfers', $postPayload);
        $this->assertTrue(in_array($postRes2->status(), [200, 201], true));

        // 3. Revaluasi Kurs Akhir Periode (D1) & Verifikasi Pasangan Jurnal
        $lot = $this->service->createFxLot(
            sourceModule: 'Trade',
            sourceEntityId: 'INV-USD-9901',
            currency: 'USD',
            foreignAmount: 100_000,
            rate: new RationalRate(15500, 1),
            lotDate: '2026-11-01',
            isAsset: true
        );
        $this->assertSame(15_500_000, $lot->carrying_functional_idr);

        $revalAction = app(RunFxRevaluationAction::class);
        $revalResults = $revalAction->execute(period: '2026-11', asOfDate: '2026-11-30');
        $this->assertNotEmpty($revalResults);

        // Verifikasi Nilai Persis Revaluasi D1:
        // USD 100.000 minor carrying 15.500.000 pada kurs 15650/1 -> revalued 15.650.000, gain +150.000
        $reval = $lot->fresh()->revaluations()->where('period', '2026-11')->first();
        $this->assertNotNull($reval);
        $this->assertSame(15_650_000, $reval->revalued_carrying_idr);
        $this->assertSame(150_000, $reval->gain_loss_idr);

        $formatAmount = fn (int $amount) => sprintf('%.18f', $amount);

        // Verifikasi Posting Ledger Revaluasi: Debit trs:fx_reval_asset_adj:IDR / Kredit trs:fx_unrealized_gain_loss:IDR
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $this->ledgerService->findAccountByCode('trs:fx_reval_asset_adj:IDR')->id,
            'amount' => $formatAmount(-150_000), // Debit dalam konvensi single-sign
        ]);
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $this->ledgerService->findAccountByCode('trs:fx_unrealized_gain_loss:IDR')->id,
            'amount' => $formatAmount(150_000), // Kredit
        ]);

        // 4. Jurnal Pembalik Otomatis Awal Periode (Auto-Reversal)
        $reverseAction = app(ReverseRevaluationsAction::class);
        $reversedCount = $reverseAction->execute(period: '2026-11');
        $this->assertGreaterThanOrEqual(1, $reversedCount);
        $this->assertTrue($reval->fresh()->is_reversed);

        // Verifikasi Posting Pembalik Persis Arah Berlawanan
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $this->ledgerService->findAccountByCode('trs:fx_reval_asset_adj:IDR')->id,
            'amount' => $formatAmount(150_000), // Kredit pembalik
        ]);
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $this->ledgerService->findAccountByCode('trs:fx_unrealized_gain_loss:IDR')->id,
            'amount' => $formatAmount(-150_000), // Debit pembalik
        ]);

        // 5. Pelunasan Realized Saat Settlement (Parsial & Penuh)
        $settleRes = $this->service->settleFxLot(
            lotId: $lot->id,
            settledForeignMinor: 40_000,
            settlementRate: new RationalRate(15700, 1),
            idempotencyKey: 'IDEM:TRS:SETTLE_PARTIAL_01'
        );
        // Realized gain diakui 80.000
        $this->assertSame(80_000, $settleRes['realized_gain_loss_idr']);
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $this->ledgerService->findAccountByCode('trs:fx_realized_gain_loss:IDR')->id,
            'amount' => $formatAmount(80_000),
        ]);

        // 6. Rekonsiliasi Bank 3-Tahap (D2)
        $bankAccount = $this->service->createBankAccount('ACC-MDR-01', 'Bank Mandiri Utama', 'IDR', 500_000_000);
        $reconAction = app(ReconcileBankStatementsAction::class);
        $reconResult = $reconAction->execute(bankAccountId: $bankAccount->id);
        $this->assertNotNull($reconResult);

        // 7. Peramalan Kas 13-Minggu Musiman (D3)
        $forecastAction = app(GenerateSeasonalCashForecastAction::class);
        $forecastResult = $forecastAction->execute(
            scenario: 'base',
            historyWeeks: [100_000_000, 80_000_000, 60_000_000, 140_000_000, 110_000_000, 90_000_000, 70_000_000, 150_000_000, 120_000_000, 100_000_000, 80_000_000, 160_000_000]
        );
        $this->assertCount(13, $forecastResult->projectedWeeks);

        // 8. Siklus State Machine Lindung Nilai (HedgeContract 8 Status)
        $hedgeSM = app(HedgeContractStateMachine::class);
        $contract = $this->service->createForwardContract(
            currency: 'USD',
            notionalForeignAmount: 100_000,
            forwardRateScaled: 15600 * 1_000_000,
            maturityDate: '2026-12-31'
        );
        $this->assertSame(HedgeStatus::DRAFT, $contract->status);

        // Draft -> Approved (Maker-Checker: pembuat != penyetuju)
        $hedgeSM->transition($contract, HedgeStatus::APPROVED, $managerUser->id, 'Disetujui komite perbendaharaan');
        $this->assertSame(HedgeStatus::APPROVED, $contract->fresh()->status);

        // Approved -> Booked -> Active
        $hedgeSM->transition($contract, HedgeStatus::BOOKED, $treasuryUser->id, 'Konfirmasi deal bank BBCA-FWD-009');
        $hedgeSM->transition($contract, HedgeStatus::ACTIVE, $treasuryUser->id, 'Mulai periode aktif lindung nilai');
        $this->assertSame(HedgeStatus::ACTIVE, $contract->fresh()->status);

        // Active -> Matured -> Settled
        $hedgeSM->transition($contract, HedgeStatus::MATURED, $treasuryUser->id, 'Jatuh tempo');
        $settleHedgeAction = app(SettleForwardContractAction::class);
        $settleHedgeAction->execute($contract, actualSpotRateScaled: 15700 * 1_000_000);
        $this->assertSame(HedgeStatus::SETTLED, $contract->fresh()->status);

        // 9. Fasilitas Kredit & Penarikan Berizin
        $facility = $this->service->createCreditFacility(
            code: 'FAC-BCA-W8',
            bankName: 'BCA Sindikasi',
            limitIdr: 10_000_000_000,
            interestPercent: 8.5,
            maxDebtEquity: 2.5
        );
        $drawAction = app(DrawFacilityAction::class);
        $drawResult = $drawAction->execute(
            facility: $facility,
            amountIdr: 1_000_000_000,
            currentDerPermille: 1800, // 1.80x < 2.50x (covenant aman)
            idempotencyKey: 'IDEM:TRS:DRAW_001'
        );
        $this->assertSame(1_000_000_000, $drawResult['drawn_amount']);
        $this->assertFalse($drawResult['covenant_breached']);

        // 10. Cash Pooling Sweep Zero-Balancing
        $headerAcc = $this->service->createBankAccount('ACC-HOLDING-01', 'Holding Header BCA', 'IDR', 100_000_000);
        $subAcc = $this->service->createBankAccount('ACC-SUBSID-01', 'Subsid Operasional Mandiri', 'IDR', 80_000_000);
        $pool = $this->service->setupCashPool(
            poolName: 'Central Cash Pool',
            headerAccountId: $headerAcc->id,
            subAccountId: $subAcc->id,
            targetBalanceIdr: 50_000_000
        );

        $sweepAction = app(ExecuteCashPoolSweepAction::class);
        $sweptAmount = $sweepAction->execute($pool);
        $this->assertSame(30_000_000, $sweptAmount);
        $this->assertSame(50_000_000, $subAcc->fresh()->balance);
        $this->assertSame(130_000_000, $headerAcc->fresh()->balance);
    }

    public function test_mt940_idempotent_import_covenant_waiver_petty_cash_imprest_and_cross_module_fx_feeds(): void
    {
        $maker = User::factory()->create(['name' => 'Maker Treasury', 'role' => 'treasury']);
        $checker = User::factory()->create(['name' => 'Checker Treasury', 'role' => 'treasury']);

        $this->service->registerCurrency('USD', 'US Dollar', '$', 2);
        $this->service->registerCurrency('IDR', 'Indonesian Rupiah', 'Rp', 0);
        $this->service->recordExchangeRate('USD', 'IDR', '2026-11-30', 15800, 1, 'closing');

        // 1. MT940 Idempotent Statement Import
        $bankAcc = $this->service->createBankAccount('ACC-MT940-01', 'BCA Corporate', 'IDR', 200_000_000);
        $mt940Sample = ":20:STMT20261130\n:25:ACC-MT940-01\n:28C:001/001\n:60F:C261101IDR200000000,00\n:61:2611151115C15000000,00NTRFINV-MT940-01//BANKREF001\n:86:SETTLEMENT EXPORT INVOICE 01\n:61:2611161116D2500000,00NMSCFEES-MT940-02//BANKREF002\n:86:MONTHLY CUSTODY FEE\n:62F:C261130IDR212500000,00";

        $importService = app(BankStatementImportService::class);
        $batch1 = $importService->importMt940Content($bankAcc, $mt940Sample, 'stmt-nov.mt940', $maker->id);
        $this->assertFalse($batch1['is_duplicate_batch']);
        $this->assertSame(2, $batch1['imported_count']);

        // Re-import file MT940 yang sama -> idempoten (0 baris baru)
        $batch2 = $importService->importMt940Content($bankAcc, $mt940Sample, 'stmt-nov.mt940', $maker->id);
        $this->assertTrue($batch2['is_duplicate_batch']);
        $this->assertSame(0, $batch2['imported_count']);

        // 2. Covenant Waiver Four-Eyes & Interest Accrual
        $facility = $this->service->createCreditFacility('FAC-WAIVER-01', 'Mandiri Corporate', 8_000_000_000, 9.0, 2.0);
        $govService = app(TreasuryCovenantGovernanceService::class);

        $waiver = $govService->requestCovenantWaiver(
            facility: $facility,
            breachedDerPermil: 2350,
            waivedMaxDerPermil: 2500,
            validUntilDate: '2099-12-31',
            reason: 'Ekspansi modal kerja musiman Q4',
            requestedByUserId: $maker->id,
            customWaiverCode: 'BMRI-WVR-2026-119'
        );
        $this->assertSame('pending', $waiver->status);

        $approvedWaiver = $govService->approveCovenantWaiver($waiver, $checker->id);
        $this->assertSame('approved', $approvedWaiver->status);

        // Dengan waiver aktif (2500 permil), penarikan pada DER 2350 permil diizinkan
        $drawAction = app(DrawFacilityAction::class);
        $drawRes = $drawAction->execute($facility, 1_000_000_000, 2350, 'IDEM:TRS:DRAW_WAIVED_01');
        $this->assertSame(1_000_000_000, $drawRes['drawn_amount']);

        // Akrual bunga fasilitas 30 hari
        $interestIdr = $govService->accrueFacilityInterest($facility->fresh(), 30, '2026-11');
        $this->assertGreaterThan(0, $interestIdr);

        // 3. Kas Kecil Imprest & Replenishment Opname Fisik
        $pettyAcc = BankAccount::create([
            'account_number' => 'PC-HO-01',
            'bank_name' => 'Kas Kecil Kantor Pusat',
            'currency' => 'IDR',
            'balance' => 10_000_000,
            'functional_balance_idr' => 10_000_000,
            'is_petty_cash' => true,
            'imprest_limit' => 10_000_000,
        ]);

        $pcvAction = app(ProcessPettyCashVoucherAction::class);
        $voucher = $pcvAction->submitVoucher(
            bankAccount: $pettyAcc,
            amount: 1_500_000,
            payee: 'Kurir Ekspor Nusantara',
            expenseAccountCode: 'trs:petty_cash_expense:IDR',
            description: 'Biaya kurir dokumen ekspor',
            creatorId: $maker->id
        );
        $approvedVoucher = $pcvAction->approveAndReimburse($voucher, $checker->id);
        $this->assertSame('reimbursed', $approvedVoucher->status);
        $this->assertSame(8_500_000, (int) $pettyAcc->fresh()->balance);

        // Opname fisik menemukan Rp 8.450.000 (selisih kurang Rp 50.000) -> Replenish kembali ke Rp 10.000.000
        $replenishment = $pcvAction->replenishImprestFund(
            pettyCashAccount: $pettyAcc->fresh(),
            physicalCashCountedIdr: 8_450_000,
            approverId: $checker->id,
            creatorId: $maker->id
        );
        $this->assertSame(1_550_000, (int) $replenishment->replenished_amount_idr);
        $this->assertSame(-50_000, (int) $replenishment->shortage_overage_idr);
        $this->assertSame(10_000_000, (int) $pettyAcc->fresh()->balance);

        // 4. Cross-Module FxMonetaryItemFeed Aggregation
        $crossModuleRes = $govService->aggregateAndRevalueCrossModuleFeeds('2026-11', '2026-11-30');
        $this->assertSame('2026-11', $crossModuleRes['period']);
        $this->assertArrayHasKey('ingested_items_count', $crossModuleRes);
    }
}
