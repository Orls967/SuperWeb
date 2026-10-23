<?php

declare(strict_types=1);

namespace Modules\TradeFinance\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\TradeFinance\Application\Actions\DiscountSupplyChainFinanceInvoiceAction;
use Modules\TradeFinance\Application\Actions\IssueDocumentaryLetterOfCreditAction;
use Modules\TradeFinance\Application\Actions\VerifyLcDocumentPresentationAction;
use Tests\TestCase;

class TradeFinanceOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_issue_documentary_letter_of_credit_creates_valid_record(): void
    {
        $action = app(IssueDocumentaryLetterOfCreditAction::class);

        $lc = $action->execute([
            'applicant_name' => 'PT Nusantara Petrochem',
            'beneficiary_name' => 'Singapore Refining Corp',
            'issuing_bank' => 'Bank Mandiri',
            'advising_bank' => 'DBS Singapore',
            'currency' => 'USD',
            'amount_foreign' => 200000,
            'amount_functional_idr' => 3000000000,
            'issue_date' => '2026-10-01',
            'expiry_date' => '2026-12-31',
            'type' => 'sight',
            'tenor_days' => 0,
        ]);

        $this->assertNotNull($lc->id);
        $this->assertStringStartsWith('LC-', $lc->lc_number);
        $this->assertSame('issued', $lc->status);
        $this->assertSame(200000, $lc->amount_foreign);
        $this->assertSame(3000000000, $lc->amount_functional_idr);
    }

    public function test_issue_documentary_letter_of_credit_validates_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $action = app(IssueDocumentaryLetterOfCreditAction::class);

        $action->execute([
            'applicant_name' => 'PT Test',
            'beneficiary_name' => 'Supplier Inc',
            'issuing_bank' => 'Bank A',
            'advising_bank' => 'Bank B',
            'amount_foreign' => 0,
            'amount_functional_idr' => 1000,
            'issue_date' => '2026-10-01',
            'expiry_date' => '2026-10-15',
        ]);
    }

    public function test_verify_lc_document_presentation_clean_presentation(): void
    {
        $action = app(IssueDocumentaryLetterOfCreditAction::class);
        $lc = $action->execute([
            'applicant_name' => 'PT Astra Agro',
            'beneficiary_name' => 'Rotterdam Port Foods',
            'issuing_bank' => 'BNI',
            'advising_bank' => 'ING Bank',
            'currency' => 'EUR',
            'amount_foreign' => 100000,
            'amount_functional_idr' => 1700000000,
            'issue_date' => '2026-10-01',
            'expiry_date' => '2026-11-30',
        ]);

        $verifyAction = app(VerifyLcDocumentPresentationAction::class);
        $res = $verifyAction->execute($lc, [
            [
                'doc_name' => 'Bill of Lading',
                'document_number' => 'BL-2026-001',
                'has_discrepancy' => false,
            ],
            [
                'doc_name' => 'Certificate of Origin',
                'document_number' => 'COO-ID-2026',
                'has_discrepancy' => false,
            ],
        ]);

        $this->assertSame('presented', $res['presentation_status']);
        $this->assertSame(2, $res['total_documents']);
        $this->assertSame(0, $res['unwaived_discrepancies']);
        $this->assertSame('presented', $lc->fresh()->status);
    }

    public function test_verify_lc_document_presentation_flags_discrepancies(): void
    {
        $action = app(IssueDocumentaryLetterOfCreditAction::class);
        $lc = $action->execute([
            'applicant_name' => 'PT Mitra Dagang',
            'beneficiary_name' => 'Guangzhou Electronics Ltd',
            'issuing_bank' => 'BCA',
            'advising_bank' => 'Bank of China',
            'currency' => 'USD',
            'amount_foreign' => 50000,
            'amount_functional_idr' => 750000000,
            'issue_date' => '2026-10-01',
            'expiry_date' => '2026-12-31',
        ]);

        $verifyAction = app(VerifyLcDocumentPresentationAction::class);
        $res = $verifyAction->execute($lc, [
            [
                'doc_name' => 'Commercial Invoice',
                'document_number' => 'INV-9901',
                'has_discrepancy' => true,
                'discrepancy_details' => 'Invoice total does not match LC exact terms',
                'is_waived_by_applicant' => false,
            ],
        ]);

        $this->assertSame('discrepancies_found', $res['presentation_status']);
        $this->assertSame(1, $res['discrepancies_count']);
        $this->assertSame(1, $res['unwaived_discrepancies']);
        $this->assertSame('discrepancies_found', $lc->fresh()->status);
    }

    public function test_discount_supply_chain_finance_invoice_calculation(): void
    {
        $action = app(DiscountSupplyChainFinanceInvoiceAction::class);

        // Principal: 1,000,000,000 IDR (1 Billion)
        // Rate: 7.20% per annum
        // Tenor: 90 days
        // Expected discount = round((1,000,000,000 * 0.072 * 90) / 360) = 18,000,000 IDR
        // Net disbursement = 982,000,000 IDR
        $res = $action->execute([
            'borrower_name' => 'PT Pemasok Komponen Presisi',
            'principal_amount_idr' => 1000000000,
            'annual_rate_percent' => 7.20,
            'tenor_days' => 90,
        ]);

        $this->assertSame(1000000000, $res['principal_amount_idr']);
        $this->assertSame(18000000, $res['discount_charge_idr']);
        $this->assertSame(982000000, $res['net_disbursement_idr']);
        $this->assertNotNull($res['loan']->id);
        $this->assertSame('scf', $res['loan']->facility_type);
        $this->assertSame('disbursed', $res['loan']->status);
    }

    public function test_trade_finance_operations_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Dashboard
        $respDash = $this->get('/trade-finance/operations');
        $respDash->assertOk();

        // 2. Issue LC via API
        $respLc = $this->postJson('/trade-finance/operations/lc/issue', [
            'applicant_name' => 'PT Sejahtera Ekspor',
            'beneficiary_name' => 'Apex Global Ltd',
            'issuing_bank' => 'Mandiri',
            'advising_bank' => 'HSBC HK',
            'amount_foreign' => 150000,
            'amount_functional_idr' => 2250000000,
            'issue_date' => '2026-10-01',
            'expiry_date' => '2026-12-15',
            'type' => 'sight',
        ]);
        $respLc->assertStatus(201);
        $lcId = $respLc->json('letter_of_credit.id');

        // 3. Verify Presentation via API
        $respPres = $this->postJson("/trade-finance/operations/lc/{$lcId}/verify-presentation", [
            'documents' => [
                [
                    'doc_name' => 'Bill of Lading',
                    'document_number' => 'BL-98124',
                    'has_discrepancy' => false,
                ],
            ],
        ]);
        $respPres->assertOk();
        $this->assertSame('presented', $respPres->json('data.presentation_status'));

        // 4. Discount SCF via API
        $respScf = $this->postJson('/trade-finance/operations/scf/discount', [
            'borrower_name' => 'PT Vendor Unggul',
            'principal_amount_idr' => 500000000,
            'annual_rate_percent' => 8.0,
            'tenor_days' => 60,
        ]);
        $respScf->assertStatus(201);
        $this->assertArrayHasKey('net_disbursement_idr', $respScf->json('data'));
    }
}
