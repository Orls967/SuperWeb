<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseFinance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\EnterpriseFinance\Domain\Models\EfCapexProposal;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Tests\TestCase;

class EnterpriseFinanceIdorSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_access_or_manipulate_foreign_department_budget(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unauthorizedUser = User::factory()->create(['role' => 'procurement']);

        $budget = EnterpriseBudget::create([
            'budget_code' => 'BGT-2026-CONFIDENTIAL',
            'fiscal_year' => '2026',
            'cost_center_code' => 'BOARD_OF_DIRECTORS',
            'account_code' => '6901-RENUMERATION',
            'allocated_amount_idr' => 1_000_000_000,
            'encumbered_amount_idr' => 0,
            'spent_amount_idr' => 0,
            'control_type' => 'HARD_STOP',
            'status' => 'ACTIVE',
        ]);

        // Percobaan manipulasi anggaran oleh pengguna non-otoritas ditolak
        $response = $this->actingAs($unauthorizedUser)->postJson("/api/enterprise-finance/budgets/{$budget->id}/spend", [
            'amount_idr' => 50_000_000,
        ]);

        // Rute diproteksi oleh middleware role
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_unauthorized_user_cannot_approve_capex_proposal(): void
    {
        $unauthorizedUser = User::factory()->create(['role' => 'driver']);

        $proposal = EfCapexProposal::create([
            'id' => (string) Str::uuid(),
            'proposal_code' => 'CPX-2026-EXPANSION',
            'line_code' => 'LINE_01',
            'project_title' => 'Pabrik Perakitan Mesin Kendaraan Niaga',
            'requested_budget_minor' => 5_000_000_000,
            'allocated_treasury_budget_minor' => 0,
            'actual_spent_minor' => 0,
            'irr_pct' => 16.5,
            'npv_minor' => 1_200_000_000,
            'esg_score' => 88.0,
            'status' => 'SUBMITTED',
        ]);

        $response = $this->actingAs($unauthorizedUser)->postJson("/api/enterprise-finance/capex/{$proposal->proposal_code}/approve", [
            'allocated_minor' => 5_000_000_000,
        ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }
}
