<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Intercompany\Application\Actions\CreateMirroredIntercompanyTransactionAction;
use Modules\Intercompany\Application\Actions\GenerateBilateralEliminationJournalAction;
use Modules\Intercompany\Application\Actions\IssueIntercompanyLoanAction;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Application\Services\IntercompanyService;
use Modules\Intercompany\Domain\Algorithms\TransferPricingMarkupCalculator;
use Modules\Intercompany\Domain\Enums\IntercompanyTransactionStatus;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Modules\Intercompany\Domain\StateMachines\IntercompanyTransactionStateMachine;
use Tests\TestCase;

class IntercompanyEndToEndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_full_intercompany_lifecycle_end_to_end_with_exact_ledger_postings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $controller = User::factory()->create(['role' => 'treasury']);

        // 1. Inisialisasi Master Entitas Legal Grup (52.1)
        $holding = IntercompanyEntity::create([
            'entity_code' => 'HOLDING',
            'legal_name' => 'PT Konglomerasi Utama Nusantara',
            'country_code' => 'ID',
            'functional_currency' => 'IDR',
            'tax_id' => '01.111.111.1-111.000',
            'ownership_percent_permille' => 100000,
            'is_parent' => true,
            'is_active' => true,
        ]);

        $subA = IntercompanyEntity::create([
            'entity_code' => 'SUB_A',
            'legal_name' => 'PT Manufaktur Komponen Presisi',
            'country_code' => 'ID',
            'functional_currency' => 'IDR',
            'tax_id' => '02.222.222.2-222.000',
            'ownership_percent_permille' => 100000,
            'is_parent' => false,
            'is_active' => true,
        ]);

        $subB = IntercompanyEntity::create([
            'entity_code' => 'SUB_B',
            'legal_name' => 'PT Distribusi Logistik Niaga',
            'country_code' => 'ID',
            'functional_currency' => 'IDR',
            'tax_id' => '03.333.333.3-333.000',
            'ownership_percent_permille' => 80000, // 80% Induk, 20% NCI
            'is_parent' => false,
            'is_active' => true,
        ]);

        // 2. Registrasi Aturan Transfer Pricing & Validasi Arm's Length (52.2, D4)
        $tpRule = TransferPricingRule::create([
            'rule_code' => 'TPR-AUTO-01',
            'product_category' => 'ELECTRONICS',
            'tp_method' => 'CPM',
            'min_arms_length_margin_percent' => 6.0,
            'max_arms_length_margin_percent' => 14.0,
            'min_margin_bps' => 600,
            'max_margin_bps' => 1400,
            'median_margin_bps' => 1000,
            'benchmark_industry_source' => 'OECD_STAT_2026',
            'is_active' => true,
        ]);

        $calculator = new TransferPricingMarkupCalculator;
        $eval = $calculator->evaluateMargin(
            method: 'CPM',
            costMinor: 10_000_000,
            actualPriceMinor: 11_000_000, // 10% markup = 1000 bps (tepat median)
            benchmarkMarginsBps: [600, 800, 1000, 1200, 1400]
        );
        $this->assertTrue($eval->isCompliant());
        $this->assertSame('COMPLIANT', $eval->status);

        // 3. Pembuatan Transaksi Cermin Atomik & Posting Single-Sign Ledger (52.1)
        $action = app(CreateMirroredIntercompanyTransactionAction::class);
        $tx = $action->execute([
            'selling_entity' => 'SUB_A',
            'buying_entity' => 'SUB_B',
            'description' => 'Pasokan Komponen Elektronika Batch 1',
            'amount_idr' => 11_000_000,
            'currency' => 'IDR',
            'tp_rule_id' => $tpRule->id,
            'created_by' => $admin->id,
        ]);

        $this->assertInstanceOf(IntercompanyTransaction::class, $tx);
        $this->assertSame('SUB_A', $tx->selling_entity);
        $this->assertSame('SUB_B', $tx->buying_entity);
        $this->assertSame(11_000_000, (int) $tx->amount_idr);

        // 4. State Machine Progression Transaksi Intercompany
        $sm = new IntercompanyTransactionStateMachine($tx);
        $sm->transitionTo(IntercompanyTransactionStatus::SUBMITTED, $admin->id, 'Diajukan staf akuntansi');
        $sm->transitionTo(IntercompanyTransactionStatus::ARM_LENGTH_VALIDATED, $controller->id, 'Disetujui komite TP');
        $sm->transitionTo(IntercompanyTransactionStatus::POSTED_SELLER, $controller->id, 'Posting buku penjual');
        $sm->transitionTo(IntercompanyTransactionStatus::POSTED_BUYER, $controller->id, 'Posting buku pembeli cermin');

        $this->assertSame(IntercompanyTransactionStatus::POSTED_BUYER, $tx->refresh()->statusEnum());

        // 5. Penerbitan Pinjaman Intercompany & Akrual Bunga PPh 23 (52.1, D6)
        $loanAction = app(IssueIntercompanyLoanAction::class);
        $loan = $loanAction->execute([
            'lender_entity' => 'HOLDING',
            'borrower_entity' => 'SUB_A',
            'principal_idr' => 120_000_000,
            'base_rate_bps' => 600,
            'spread_bps' => 50, // 6,50%
            'withholding_tax_bps' => 1500, // 15% PPh 23
            'periods' => 12,
            'start_date' => '2026-10-01',
            'due_date' => '2027-10-01',
            'operator_id' => $controller->id,
        ]);

        $this->assertInstanceOf(IntercompanyLoan::class, $loan);
        $this->assertSame(120_000_000, (int) $loan->principal_idr);
        $this->assertCount(12, $loan->schedules);

        // 6. Eliminasi Bilateral Akhir Periode (52.4, D5)
        $eliminationAction = app(GenerateBilateralEliminationJournalAction::class);
        $eliminationResult = $eliminationAction->execute([
            'period' => '2026-10',
            'operator_id' => $controller->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $eliminationResult->eliminationEntriesCount);
        $this->assertSame(0, $eliminationResult->discrepancyIdr);

        // Transaksi bergeser ke status RECONCILED lalu ELIMINATED
        $sm->transitionTo(IntercompanyTransactionStatus::RECONCILED, $controller->id, 'Rekonsiliasi saldo cut-off');
        $sm->transitionTo(IntercompanyTransactionStatus::ELIMINATED, $controller->id, 'Jurnal eliminasi konsolidasi diposting');
        $this->assertSame(IntercompanyTransactionStatus::ELIMINATED, $tx->refresh()->statusEnum());

        // 7. Audit Integritas Saldo Buku Besar
        $icService = app(IntercompanyService::class);
        $audit = $icService->auditIntercompany();
        $this->assertSame('OK', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
