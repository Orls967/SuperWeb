<?php

declare(strict_types=1);

namespace Modules\Intercompany\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyService;
use Tests\TestCase;

class IntercompanyTest extends TestCase
{
    use RefreshDatabase;

    protected IntercompanyService $icService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->icService = app(IntercompanyService::class);
    }

    public function test_52_1_mirror_transactions_and_intercompany_loan(): void
    {
        // Mirror transaction: PT Super Manufaktur -> PT Super Store
        $tx = $this->icService->recordMirrorTransaction(
            sellingEntity: 'PT Super Manufaktur',
            buyingEntity: 'PT Super Store',
            description: 'Penjualan Batch Spareparts Otomotif',
            amountIdr: 450_000_000
        );

        $this->assertDatabaseHas('ic_transactions', [
            'id' => $tx->id,
            'selling_entity' => 'PT Super Manufaktur',
            'buying_entity' => 'PT Super Store',
            'amount_idr' => 450_000_000,
            'status' => 'matched',
        ]);

        // Disallow transaction between identical entity
        $this->expectException(InvalidArgumentException::class);
        $this->icService->recordMirrorTransaction(
            sellingEntity: 'PT Super Holding',
            buyingEntity: 'PT Super Holding',
            description: 'Self transaction error',
            amountIdr: 10_000_000
        );
    }

    public function test_52_1_intercompany_loan_creation(): void
    {
        $loan = $this->icService->issueIntercompanyLoan(
            lender: 'PT Super Holding',
            borrower: 'PT Super Logistik',
            principalIdr: 1_500_000_000,
            armsLengthRate: 6.25,
            dueDate: '2027-10-06'
        );

        $this->assertDatabaseHas('ic_loans', [
            'id' => $loan->id,
            'principal_idr' => 1_500_000_000,
            'arms_length_interest_rate' => 6.25,
            'status' => 'active',
        ]);
    }

    public function test_52_2_transfer_pricing_rule_and_margin_validation(): void
    {
        $this->icService->registerTransferPricingRule(
            category: 'ELECTRONIC_COMPONENTS',
            tpMethod: 'CPM',
            minMargin: 8.0,
            maxMargin: 15.0,
            benchmarkSource: 'OECD_AUTOMOTIVE_2026'
        );

        // Case A: Cost 100jt, IC Price 112jt -> Margin = 12% (Compliant)
        $resA = $this->icService->validateTransferPricingMargin(
            category: 'ELECTRONIC_COMPONENTS',
            costIdr: 100_000_000,
            intercompanyPriceIdr: 112_000_000
        );

        $this->assertTrue($resA['is_compliant']);
        $this->assertEquals('COMPLIANT', $resA['status']);
        $this->assertEquals(12.0, $resA['actual_margin_percent']);

        // Case B: Cost 100jt, IC Price 125jt -> Margin = 25% (Out of Range)
        $resB = $this->icService->validateTransferPricingMargin(
            category: 'ELECTRONIC_COMPONENTS',
            costIdr: 100_000_000,
            intercompanyPriceIdr: 125_000_000
        );

        $this->assertFalse($resB['is_compliant']);
        $this->assertEquals('OUT_OF_RANGE', $resB['status']);
        $this->assertEquals(25.0, $resB['actual_margin_percent']);
    }

    public function test_52_4_consolidation_elimination_entry(): void
    {
        $elim = $this->icService->postEliminationEntry(
            period: '2026-10',
            eliminationType: 'RECIPROCAL_AR_AP',
            debitAccount: 'ic:ap:PT_STORE',
            creditAccount: 'ic:ar:PT_MANUFACTURING',
            amountIdr: 450_000_000
        );

        $this->assertDatabaseHas('ic_elimination_entries', [
            'id' => $elim->id,
            'period' => '2026-10',
            'amount_idr' => 450_000_000,
            'status' => 'posted',
        ]);
    }

    public function test_52_5_non_controlling_interest_calculation(): void
    {
        // Subsidiary with 80% parent ownership, 20% NCI, net income 1,000,000,000 IDR
        $nci = $this->icService->calculateNciShare(
            subsidiaryName: 'PT Indo Global Parts',
            parentSharePercent: 80.0,
            subsidiaryNetIncomeIdr: 1_000_000_000
        );

        $this->assertEquals(20.0, $nci->nci_ownership_percent);
        $this->assertEquals(200_000_000, $nci->nci_share_net_income_idr);
        $this->assertDatabaseHas('ic_subsidiary_nci', [
            'subsidiary_name' => 'PT Indo Global Parts',
            'nci_share_net_income_idr' => 200_000_000,
        ]);
    }

    public function test_52_7_intercompany_audit_and_command(): void
    {
        $this->icService->recordMirrorTransaction(
            sellingEntity: 'PT Logistik Ekspres',
            buyingEntity: 'PT Duta Resto',
            description: 'Freight Distribution Service',
            amountIdr: 25_000_000
        );

        $audit = $this->icService->auditIntercompany();
        $this->assertEquals('OK', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
        $this->assertGreaterThanOrEqual(1, $audit['transaction_count']);

        $this->artisan('group:audit')
            ->expectsOutputToContain('Group Consolidation audit PASSED with 0 discrepancy.')
            ->assertExitCode(0);
    }

    public function test_52_8_http_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('intercompany.index'))
            ->assertOk()
            ->assertSee('Konsolidasi Grup & Transaksi Antar-Perusahaan');

        $this->actingAs($user)
            ->get(route('intercompany.transactions'))
            ->assertOk()
            ->assertSee('Transaksi Cermin');
    }
}
