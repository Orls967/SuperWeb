<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Services\OutboxBusService;
use Modules\Core\Domain\Models\OutboxMessage;
use Modules\Core\Domain\Models\OutboxSubscription;
use Modules\Hcm\Application\Services\HcmService;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;
use Modules\Integration\Application\Services\IntegrationService;
use Modules\Party\Application\Services\PartyService;
use Modules\Party\Application\Services\SanctionScreeningService;
use Modules\Party\Domain\Enums\SanctionCheckStatus;
use Tests\TestCase;

class K32ScalabilityRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_k32_hcm_audit_handles_chunked_payrolls_and_flags_corruptions(): void
    {
        /** @var HcmService $hcm */
        $hcm = app(HcmService::class);

        // Clean state
        $resClean = $hcm->auditHcm();
        $this->assertSame('HEALTHY', $resClean['status']);
        $this->assertSame(0, $resClean['discrepancies']);

        $emp = Employee::create([
            'employee_number' => 'EMP-SCL-01',
            'name' => 'Scale Employee 1',
            'nik' => '3171012345670001',
            'position' => 'Principal Engineer',
            'employment_type' => 'PKWTT',
            'basic_salary_idr' => 20000000,
        ]);

        // Insert valid payroll
        Payroll::create([
            'employee_id' => $emp->id,
            'period' => '2026-10',
            'gross_salary_idr' => 20000000,
            'deductions_idr' => 3000000,
            'net_salary_idr' => 17000000,
            'pph21_idr' => 1000000,
            'bpjs_tk_idr' => 1000000,
            'bpjs_kes_idr' => 1000000,
            'status' => 'approved',
        ]);

        $resValid = $hcm->auditHcm();
        $this->assertSame('HEALTHY', $resValid['status']);
        $this->assertSame(1, $resValid['payroll_count']);
        $this->assertSame(0, $resValid['discrepancies']);

        // Insert corrupted payroll (net salary discrepancy)
        Payroll::create([
            'employee_id' => $emp->id,
            'period' => '2026-11',
            'gross_salary_idr' => 20000000,
            'deductions_idr' => 3000000,
            'net_salary_idr' => 99999999, // Mismatched!
            'pph21_idr' => 1000000,
            'bpjs_tk_idr' => 1000000,
            'bpjs_kes_idr' => 1000000,
            'status' => 'approved',
        ]);

        $resCorrupt = $hcm->auditHcm();
        $this->assertSame('UNHEALTHY', $resCorrupt['status']);
        $this->assertSame(2, $resCorrupt['payroll_count']);
        $this->assertGreaterThan(0, $resCorrupt['discrepancies']);
    }

    public function test_k32_outbox_filters_subscriptions_at_database_level(): void
    {
        /** @var OutboxBusService $outbox */
        $outbox = app(OutboxBusService::class);

        // Sub 1: inactive (should be skipped)
        OutboxSubscription::create([
            'name' => 'Inactive Sub',
            'target_type' => 'webhook',
            'target' => 'https://example.com/webhook1',
            'secret' => 'sec1',
            'events' => ['order.placed'],
            'is_active' => false,
        ]);

        // Sub 2: active with wildcard '*'
        OutboxSubscription::create([
            'name' => 'Wildcard Sub',
            'target_type' => 'webhook',
            'target' => 'https://example.com/webhook2',
            'secret' => 'sec2',
            'events' => ['*'],
            'is_active' => true,
        ]);

        // Sub 3: active with mismatched event
        OutboxSubscription::create([
            'name' => 'Other Event Sub',
            'target_type' => 'webhook',
            'target' => 'https://example.com/webhook3',
            'secret' => 'sec3',
            'events' => ['invoice.paid'],
            'is_active' => true,
        ]);

        $msg = OutboxMessage::create([
            'event_type' => 'order.placed',
            'aggregate_type' => 'Order',
            'aggregate_id' => 'ORD-101',
            'payload' => ['id' => 101],
            'idempotency_key' => 'IDEMP-TEST-OUTBOX-1',
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $outbox->processMessage($msg);

        // The message should have been dispatched to the wildcard subscription
        $this->assertSame(1, $msg->attempts);
        $this->assertSame(1, DB::table('core_outbox_dispatches')->count());
    }

    public function test_k32_integration_audit_queries_with_deterministic_ordering(): void
    {
        /** @var IntegrationService $service */
        $service = app(IntegrationService::class);

        $result = $service->auditIntegration();
        $this->assertSame('OK', $result['status']);
        $this->assertSame(0, $result['discrepancy_count']);
    }

    public function test_k32_sanction_screening_uses_indexed_prefix_and_exact_match(): void
    {
        /** @var PartyService $partyService */
        $partyService = app(PartyService::class);
        /** @var SanctionScreeningService $screeningService */
        $screeningService = app(SanctionScreeningService::class);

        DB::table('pty_sanctions_lists')->insert([
            'source' => 'un_scale_test',
            'entry_type' => 'entity',
            'name' => 'Corrupt Cartel Conglomerate',
            'name_normalized' => 'corrupt cartel conglomerate',
            'identifier' => 'TAX-SCALE-001',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Exact match
        $exactParty = $partyService->create([
            'name' => 'Corrupt Cartel Conglomerate',
            'npwp' => 'TAX-DIFF-002',
        ]);

        $checkExact = $screeningService->screen($exactParty, 'onboarding');
        $this->assertSame(SanctionCheckStatus::Hit, $checkExact->status);
        $this->assertEquals(100.0, (float) $checkExact->match_score);

        // Identifier match
        $idParty = $partyService->create([
            'name' => 'Completely Different Name Enterprise',
            'npwp' => 'TAX-SCALE-001',
        ]);

        $checkId = $screeningService->screen($idParty, 'onboarding');
        $this->assertSame(SanctionCheckStatus::Hit, $checkId->status);
        $this->assertEquals(100.0, (float) $checkId->match_score);

        // Clean party
        $cleanParty = $partyService->create([
            'name' => 'PT Nusantara Sejahtera Abadi',
            'npwp' => 'TAX-CLEAN-999',
        ]);

        $checkClean = $screeningService->screen($cleanParty, 'onboarding');
        $this->assertSame(SanctionCheckStatus::Clear, $checkClean->status);
    }
}
