<?php

declare(strict_types=1);

namespace Modules\TradeFinance\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\TradeFinance\Application\Services\TradeFinanceService;
use Modules\Treasury\Application\Services\TreasuryService;
use Tests\TestCase;

class TradeFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected TradeFinanceService $tfService;

    protected TreasuryService $treasuryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tfService = app(TradeFinanceService::class);
        $this->treasuryService = app(TreasuryService::class);

        // Setup FX rates: 1 USD = 15,000 IDR
        $this->treasuryService->registerCurrency('USD', 'US Dollar');
        $this->treasuryService->registerCurrency('IDR', 'Indonesian Rupiah');
        $this->treasuryService->recordExchangeRate('USD', 'IDR', '2026-10-06', 15_000_000_000, 1_000_000);
    }

    public function test_50_1_and_50_7_letter_of_credit_issuance_and_ledger_memorandum(): void
    {
        $lc = $this->tfService->issueLetterOfCredit(
            applicantName: 'PT Nusantara Global Trade',
            beneficiaryName: 'Tokyo Heavy Industries Ltd',
            issuingBank: 'Bank Mandiri',
            advisingBank: 'MUFG Bank Tokyo',
            foreignAmount: 50_000, // 50,000 USD
            currency: 'USD',
            expiryDate: '2026-12-31',
            type: 'sight',
            tenorDays: 0
        );

        $this->assertDatabaseHas('tf_letters_of_credit', [
            'id' => $lc->id,
            'currency' => 'USD',
            'amount_foreign' => 50_000,
            'amount_functional_idr' => 750_000_000, // 50,000 * 15,000
            'status' => 'issued',
        ]);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'idempotency_key' => 'LC_EXP_'.$lc->lc_number,
            'type' => 'TF_LC_ISSUED',
        ]);
    }

    public function test_50_2_lc_document_presentation_discrepancy_and_waiver(): void
    {
        $lc = $this->tfService->issueLetterOfCredit(
            applicantName: 'PT Mega Importir',
            beneficiaryName: 'Shanghai Tech Corp',
            issuingBank: 'BCA',
            advisingBank: 'Bank of China',
            foreignAmount: 10_000,
            currency: 'USD',
            expiryDate: '2026-11-30'
        );

        // Present clean Bill of Lading
        $doc1 = $this->tfService->presentDocument(
            lc: $lc,
            docName: 'Bill of Lading',
            docNumber: 'BL-9921',
            hasDiscrepancy: false
        );

        $this->assertFalse($doc1->has_discrepancy);
        $this->assertEquals('presented', $lc->fresh()->status);

        // Present Commercial Invoice with discrepancy
        $doc2 = $this->tfService->presentDocument(
            lc: $lc,
            docName: 'Commercial Invoice',
            docNumber: 'INV-8812',
            hasDiscrepancy: true,
            details: 'Goods description does not match LC clause 45A exactly'
        );

        $this->assertTrue($doc2->has_discrepancy);
        $this->assertEquals('discrepancies_found', $lc->fresh()->status);

        // Applicant waives the discrepancy
        $doc2 = $this->tfService->waiveDiscrepancy($doc2);
        $this->assertTrue($doc2->is_waived_by_applicant);
        $this->assertEquals('accepted', $lc->fresh()->status);
    }

    public function test_50_3_documentary_collection_dp_da(): void
    {
        $col = $this->tfService->createCollection(
            type: 'DP',
            drawer: 'PT Agro Ekspor Mandiri',
            drawee: 'Rotterdam Roasters BV',
            collectingBank: 'ING Bank Netherlands',
            amountForeign: 35_000,
            currency: 'USD',
            tenorDays: 0
        );

        $this->assertDatabaseHas('tf_documentary_collections', [
            'id' => $col->id,
            'type' => 'DP',
            'amount_foreign' => 35_000,
            'status' => 'presented',
        ]);

        $col = $this->tfService->payCollection($col);
        $this->assertEquals('paid', $col->status);
    }

    public function test_50_4_bank_guarantee_issuance_and_claim(): void
    {
        $bg = $this->tfService->issueBankGuarantee(
            type: 'performance_bond',
            issuingBank: 'Bank BNI',
            applicant: 'PT Konstruksi Prima',
            beneficiary: 'Kementerian PUPR',
            amountIdr: 500_000_000,
            effectiveDate: '2026-10-01',
            expiryDate: '2027-10-01'
        );

        $this->assertDatabaseHas('tf_bank_guarantees', [
            'id' => $bg->id,
            'type' => 'performance_bond',
            'amount_idr' => 500_000_000,
            'status' => 'active',
        ]);

        // Attempt claim higher than guarantee amount throws InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->tfService->claimBankGuarantee($bg, 600_000_000);
    }

    public function test_50_4_bank_guarantee_valid_claim(): void
    {
        $bg = $this->tfService->issueBankGuarantee(
            type: 'bid_bond',
            issuingBank: 'Bank BNI',
            applicant: 'PT Konstruksi Prima',
            beneficiary: 'PT PLN Persero',
            amountIdr: 100_000_000,
            effectiveDate: '2026-10-01',
            expiryDate: '2026-11-01'
        );

        $claimedBg = $this->tfService->claimBankGuarantee($bg, 50_000_000);
        $this->assertEquals('claimed', $claimedBg->status);
        $this->assertEquals(50_000_000, $claimedBg->claim_amount_idr);
    }

    public function test_50_5_trade_loan_disbursement_and_repayment(): void
    {
        $loan = $this->tfService->disburseTradeLoan(
            facilityType: 'pre_shipment',
            borrower: 'PT Mebel Jepara Global',
            principalIdr: 200_000_000,
            interestPercent: 7.5,
            dueDate: '2027-04-01'
        );

        $this->assertDatabaseHas('tf_trade_loans', [
            'id' => $loan->id,
            'principal_amount_idr' => 200_000_000,
            'status' => 'disbursed',
        ]);

        // Partial repayment
        $loan = $this->tfService->repayTradeLoan($loan, 100_000_000);
        $this->assertEquals('partially_repaid', $loan->status);
        $this->assertEquals(100_000_000, $loan->repaid_amount_idr);

        // Full repayment
        $loan = $this->tfService->repayTradeLoan($loan, 100_000_000);
        $this->assertEquals('settled', $loan->status);
        $this->assertEquals(200_000_000, $loan->repaid_amount_idr);
    }

    public function test_50_8_trade_finance_audit_and_command(): void
    {
        $this->tfService->issueLetterOfCredit(
            applicantName: 'PT Solusi Otomasi',
            beneficiaryName: 'Berlin Automation GmbH',
            issuingBank: 'Bank Mandiri',
            advisingBank: 'Deutsche Bank',
            foreignAmount: 25_000,
            currency: 'USD',
            expiryDate: '2026-12-31'
        );

        $audit = $this->tfService->auditTradeFinance();
        $this->assertEquals('OK', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
        $this->assertGreaterThanOrEqual(1, $audit['lc_count']);

        $this->artisan('tf:audit')
            ->expectsOutputToContain('Trade Finance audit PASSED with 0 discrepancy.')
            ->assertExitCode(0);
    }

    public function test_50_9_http_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('trade_finance.index'))
            ->assertOk()
            ->assertSee('Letter of Credit');

        $this->actingAs($user)
            ->get(route('trade_finance.lcs'))
            ->assertOk()
            ->assertSee('Letter of Credit');
    }
}
