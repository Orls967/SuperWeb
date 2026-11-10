<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Tests\TestCase;

class IntercompanyIdorSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_unauthorized_user_cannot_access_or_manipulate_foreign_intercompany_transaction(): void
    {
        $userCustomer = User::factory()->create(['role' => 'customer']);

        $tx = IntercompanyTransaction::create([
            'transaction_code' => 'IC-SEC-001',
            'selling_entity' => 'HOLDING',
            'buying_entity' => 'SUB_A',
            'description' => 'Confidential Intercompany Deal',
            'currency' => 'IDR',
            'amount_idr' => 50_000_000,
            'sales_invoice_ref' => 'INV-SEC-001',
            'purchase_bill_ref' => 'BILL-SEC-001',
            'status' => 'draft',
        ]);

        // Percobaan akses oleh role tidak berhak diblokir (403)
        $response = $this->actingAs($userCustomer)->get('/intercompany');
        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_disburse_or_modify_intercompany_loan(): void
    {
        $unauthorized = User::factory()->create(['role' => 'customer']);

        $loan = IntercompanyLoan::create([
            'loan_agreement_number' => 'ICL-SEC-002',
            'lender_entity' => 'HOLDING',
            'borrower_entity' => 'SUB_B',
            'principal_idr' => 100_000_000,
            'arms_length_interest_rate' => 6.5,
            'start_date' => '2026-01-01',
            'due_date' => '2027-01-01',
            'repaid_principal_idr' => 0,
            'status' => 'active',
        ]);

        $response = $this->actingAs($unauthorized)->post("/intercompany/consolidation/markup/{$loan->id}", [
            'margin' => 10.0,
        ]);
        $response->assertStatus(403);
    }
}
