<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseFinance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;
use Modules\EnterpriseFinance\Domain\Models\SodRule;
use Tests\TestCase;

class EnterpriseFinanceDualSourceAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_audit_passes_on_clean_data_and_fails_on_corrupted_fixture(): void
    {
        // 1. Siapkan data bersih
        EnterpriseBudget::create([
            'budget_code' => 'BGT-2026-OPS',
            'fiscal_year' => '2026',
            'cost_center_code' => 'OPERATIONS',
            'account_code' => '5101-PLANT',
            'allocated_amount_idr' => 500_000_000,
            'encumbered_amount_idr' => 100_000_000,
            'spent_amount_idr' => 300_000_000,
            'control_type' => 'HARD_STOP',
            'status' => 'ACTIVE',
        ]);

        EnterpriseTaxSummary::create([
            'period' => '2026-10',
            'tax_type' => 'PPN',
            'tax_base_idr' => 1_000_000_000,
            'input_tax_idr' => 70_000_000,
            'output_tax_idr' => 110_000_000,
            'payable_or_refundable_idr' => 40_000_000,
            'status' => 'draft',
        ]);

        SodRule::create([
            'rule_code' => 'SOD-001',
            'role_a' => 'maker',
            'role_b' => 'checker',
            'description' => 'Pemisahan pembuatan dan persetujuan',
            'risk_level' => 'CRITICAL',
            'is_active' => true,
        ]);

        ComplianceDeadline::create([
            'item_code' => 'REG-SPT-202610',
            'title' => 'SPT Masa PPN Oktober 2026',
            'regulatory_body' => 'DJP',
            'due_date' => '2026-11-30',
            'assigned_role' => 'auditor',
            'status' => 'pending',
        ]);

        // Audit kondisi bersih wajib sukses exit 0
        $cleanExitCode = Artisan::call('enterprise:audit');
        $this->assertSame(0, $cleanExitCode, 'Audit EnterpriseFinance pada kondisi bersih wajib mengembalikan exit code 0.');

        // 2. Injeksi korupsi data invarian: Ubah belanja aktual melebihi pagu Hard-Stop
        EnterpriseBudget::where('budget_code', 'BGT-2026-OPS')->update([
            'spent_amount_idr' => 600_000_000, // 100M encumbered + 600M spent > 500M allocated
        ]);

        // Audit kondisi korup wajib gagal exit 1
        $corruptedExitCode = Artisan::call('enterprise:audit');
        $this->assertSame(1, $corruptedExitCode, 'Audit EnterpriseFinance pada kondisi invarian terlanggar wajib mengembalikan exit code 1.');
    }
}
