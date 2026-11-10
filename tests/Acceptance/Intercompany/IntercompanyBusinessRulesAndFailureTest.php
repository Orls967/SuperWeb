<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Intercompany\Application\Actions\CreateMirroredIntercompanyTransactionAction;
use Modules\Intercompany\Application\Actions\IssueIntercompanyLoanAction;
use Modules\Intercompany\Application\Services\IntercompanyService;
use Modules\Intercompany\Domain\Enums\IntercompanyTransactionStatus;
use Modules\Intercompany\Domain\Exceptions\IntercompanyInvalidTransitionException;
use Modules\Intercompany\Domain\Exceptions\IntercompanyMakerCheckerViolationException;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\StateMachines\IntercompanyTransactionStateMachine;
use Tests\TestCase;

class IntercompanyBusinessRulesAndFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_identical_entities_rejected(): void
    {
        $action = app(CreateMirroredIntercompanyTransactionAction::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaksi intercompany tidak boleh antara entitas yang sama');

        $action->execute([
            'selling_entity' => 'HOLDING',
            'buying_entity' => 'HOLDING',
            'description' => 'Invalid self transaction',
            'amount_idr' => 1_000_000,
        ]);
    }

    public function test_loan_repayment_cannot_exceed_principal(): void
    {
        $service = app(IntercompanyService::class);
        $loan = $service->issueIntercompanyLoan(
            lender: 'HOLDING',
            borrower: 'SUB_A',
            principalIdr: 10_000_000,
            armsLengthRate: 6.5,
            dueDate: '2027-12-31'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pelunasan pokok tidak boleh melebihi sisa pokok');

        $loan->recordRepayment(15_000_000);
    }

    public function test_invalid_state_transition_throws_exception(): void
    {
        $entityA = IntercompanyEntity::firstOrCreate(
            ['entity_code' => 'HOLDING'],
            ['legal_name' => 'PT Holding Corp', 'country_code' => 'ID', 'functional_currency' => 'IDR', 'tax_id' => '01.000.000.0-000.000', 'ownership_percent_permille' => 100000, 'is_parent' => true]
        );
        $entityB = IntercompanyEntity::firstOrCreate(
            ['entity_code' => 'SUB_A'],
            ['legal_name' => 'PT Sub A', 'country_code' => 'ID', 'functional_currency' => 'IDR', 'tax_id' => '02.000.000.0-000.000', 'ownership_percent_permille' => 100000, 'is_parent' => false]
        );

        $tx = IntercompanyTransaction::create([
            'transaction_code' => 'IC-TEST-001',
            'selling_entity' => 'HOLDING',
            'buying_entity' => 'SUB_A',
            'description' => 'Test Transaction',
            'currency' => 'IDR',
            'amount_idr' => 5_000_000,
            'sales_invoice_ref' => 'INV-001',
            'purchase_bill_ref' => 'BILL-001',
            'status' => IntercompanyTransactionStatus::DRAFT->value,
            'created_by' => 1,
        ]);

        $sm = new IntercompanyTransactionStateMachine($tx);

        // Langsung lompat dari DRAFT ke ELIMINATED dilarang keras!
        $this->expectException(IntercompanyInvalidTransitionException::class);
        $sm->transitionTo(IntercompanyTransactionStatus::ELIMINATED, operatorId: 2, reason: 'Invalid jump');
    }

    public function test_maker_checker_violation_guard(): void
    {
        $tx = IntercompanyTransaction::create([
            'transaction_code' => 'IC-TEST-002',
            'selling_entity' => 'HOLDING',
            'buying_entity' => 'SUB_A',
            'description' => 'Test Transaction',
            'currency' => 'IDR',
            'amount_idr' => 5_000_000,
            'sales_invoice_ref' => 'INV-002',
            'purchase_bill_ref' => 'BILL-002',
            'status' => IntercompanyTransactionStatus::SUBMITTED->value,
            'created_by' => 10,
        ]);

        $sm = new IntercompanyTransactionStateMachine($tx);

        // Operator pembuat (10) mencoba menyetujui transaksi sendiri -> Maker-Checker violation!
        $this->expectException(IntercompanyMakerCheckerViolationException::class);
        $sm->transitionTo(IntercompanyTransactionStatus::ARM_LENGTH_VALIDATED, operatorId: 10, reason: 'Self approval');
    }
}
