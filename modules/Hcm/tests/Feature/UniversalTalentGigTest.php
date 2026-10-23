<?php

declare(strict_types=1);

namespace Modules\Hcm\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hcm\Application\Services\UniversalTalentGigOrchestratorService;
use Modules\Hcm\Domain\Models\Gig\UniversalGigTask;
use RuntimeException;
use Tests\TestCase;

class UniversalTalentGigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@typeshit.local', 'password' => 'secret'],
            ['id' => 10, 'name' => 'Supervisor User', 'email' => 'supervisor@typeshit.local', 'password' => 'secret'],
            ['id' => 20, 'name' => 'Gig Worker 20', 'email' => 'worker20@typeshit.local', 'password' => 'secret'],
        ]);

        foreach (['hotel', 'venue', 'mining', 'logistics', 'hospital', 'resto'] as $domain) {
            LedgerAccount::query()->firstOrCreate(
                ['code' => "{$domain}:gig_expense:IDR"],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => "{$domain} Gig Expense",
                    'asset_code' => 'IDR',
                    'kind' => 'EXPENSE',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }

        LedgerAccount::query()->firstOrCreate(
            ['code' => 'payroll:worker_payable:20:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Worker 20 Payable',
                'asset_code' => 'IDR',
                'kind' => 'LIABILITY',
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public function test_publish_gig_task_calculates_total_compensation(): void
    {
        $service = app(UniversalTalentGigOrchestratorService::class);
        $task = $service->publishGigTask('HOTEL', 'Banqueting Emergency Shift', 'Shift for annual conference', 75000, 8, 10, 'K3-HOSPITALITY-BASIC');

        $this->assertInstanceOf(UniversalGigTask::class, $task);
        $this->assertSame('HOTEL', $task->source_domain);
        $this->assertSame(600000, $task->total_compensation_idr);
        $this->assertSame('OPEN', $task->status);
    }

    public function test_claim_gig_task_enforces_k3_certificate_qualification(): void
    {
        $service = app(UniversalTalentGigOrchestratorService::class);
        $task = $service->publishGigTask('MINING', 'Pit Equipment Maintenance', 'Hydraulic hose check', 150000, 4, 10, 'K3-MINING-LEVEL2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker lacks required K3 certification');

        $service->claimGigTask($task->id, 20, 'K3-BASIC-NON-MINING');
    }

    public function test_claim_and_payout_disbursement_via_ledger(): void
    {
        $service = app(UniversalTalentGigOrchestratorService::class);
        $task = $service->publishGigTask('VENUE', 'Stage Light Rigging', 'Evening concert rig setup', 100000, 5, 10, 'K3-RIGGING');

        $claimed = $service->claimGigTask($task->id, 20, 'K3-RIGGING');
        $this->assertSame('CLAIMED', $claimed->status);
        $this->assertSame(20, $claimed->assigned_user_id);

        $payout = $service->approveAndDisburseGigPayout($task->id, 10, 'PROOF-RIGGING-PASS-001');
        $this->assertSame('PAID', $payout['status']);
        $this->assertSame(500000, $payout['net_paid_idr']);
        $this->assertSame(20, $payout['worker_id']);

        $this->assertDatabaseHas('hcm_universal_gig_tasks', [
            'id' => $task->id,
            'status' => 'PAID',
        ]);
    }
}
