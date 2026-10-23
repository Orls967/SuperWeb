<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\InterBranchFinancialClearingService;
use Modules\Hospital\Domain\Models\InterBranchClearing;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Tests\TestCase;

class InterBranchFinancialClearingTest extends TestCase
{
    use RefreshDatabase;

    private InterBranchFinancialClearingService $clearingService;

    private PharmacyBranch $branchA;

    private PharmacyBranch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearingService = app(InterBranchFinancialClearingService::class);

        $this->seed(LedgerAccountsSeeder::class);

        $this->branchA = PharmacyBranch::create([
            'branch_code' => 'BR-CENTRAL-01',
            'name' => 'Apotek Pusat Jakarta',
            'type' => 'INTERNAL',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);

        $this->branchB = PharmacyBranch::create([
            'branch_code' => 'BR-SUBURB-02',
            'name' => 'Apotek Cabang Bekasi',
            'type' => 'INTERNAL',
            'city' => 'Bekasi',
            'is_active' => true,
        ]);
    }

    public function test_inter_branch_obligation_creation_and_balanced_ledger_settlement(): void
    {
        $clearing = $this->clearingService->createClearingObligation(
            fromBranchId: $this->branchA->id,
            toBranchId: $this->branchB->id,
            referenceType: 'CROSS_BRANCH_LOT_TRANSFER',
            referenceId: 'TX-LOT-9982',
            amountIdr: 450_000
        );

        $this->assertInstanceOf(InterBranchClearing::class, $clearing);
        $this->assertSame('PENDING', $clearing->settlement_status);
        $this->assertSame(450_000, $clearing->amount_idr);

        // Execute financial clearing settlement
        $settled = $this->clearingService->settleClearing($clearing);

        $this->assertSame('SETTLED', $settled->settlement_status);

        // Verify balanced double-entry accounting in Ledger
        $clearingAccount = LedgerAccount::where('code', 'hsp:inter_branch_clearing:IDR')->first();
        $receivableAccount = LedgerAccount::where('code', 'hsp:epharmacy_receivable:IDR')->first();

        $this->assertNotNull($clearingAccount);
        $this->assertNotNull($receivableAccount);
        $this->assertEquals('-450000', (string) $clearingAccount->cached_balance);
        $this->assertEquals('450000', (string) $receivableAccount->cached_balance);
    }
}
