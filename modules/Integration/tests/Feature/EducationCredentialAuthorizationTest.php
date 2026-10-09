<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EducationCredentialAuthorizationService;
use Tests\TestCase;

class EducationCredentialAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected EducationCredentialAuthorizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EducationCredentialAuthorizationService::class);
    }

    public function test_expired_or_revoked_credential_blocks_new_assignment(): void
    {
        // 1. Issue revoked credential (384.1 & 384.4)
        $this->service->issueCredential(
            credentialCode: 'CRED-MINE-BLASTING-01',
            workerId: 'WRK-MINER-10',
            roleDomain: 'MINING',
            isActive: false,
            isRevoked: true
        );

        // 2. Attempting task assignment fails (384.4)
        try {
            $this->service->assignOperationalTask(
                assignmentCode: 'TSK-BLAST-SECTOR-4',
                workerId: 'WRK-MINER-10',
                credentialCode: 'CRED-MINE-BLASTING-01'
            );
            $this->fail('Expected exception for assignment with revoked credential');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('is inactive, expired, or revoked', $e->getMessage());
        }

        // Verify task record shows assignment blocked
        $task = DB::table('global_operational_task_assignments')->where('assignment_code', 'TSK-BLAST-SECTOR-4')->first();
        $this->assertNotNull($task);
        $this->assertFalse((bool) $task->assignment_permitted);
    }

    public function test_revocation_during_in_progress_task_triggers_safe_handoff_edge_case(): void
    {
        // 1. Issue valid credential and assign task (384.1 & 384.4)
        $this->service->issueCredential(
            credentialCode: 'CRED-PILOT-COMMERCIAL-01',
            workerId: 'WRK-PILOT-CAPTAIN-ALICE',
            roleDomain: 'AVIATION',
            isActive: true,
            isRevoked: false
        );

        $assignment = $this->service->assignOperationalTask(
            assignmentCode: 'TSK-FLIGHT-GA-882',
            workerId: 'WRK-PILOT-CAPTAIN-ALICE',
            credentialCode: 'CRED-PILOT-COMMERCIAL-01'
        );
        $this->assertTrue((bool) $assignment->assignment_permitted);

        // 2. Revocation mid-task triggers safe handoff without safety risk (384.4 & 384.5 Edge Case)
        $updatedAssignment = $this->service->revokeCredentialDuringTask(
            credentialCode: 'CRED-PILOT-COMMERCIAL-01',
            assignmentCode: 'TSK-FLIGHT-GA-882',
            alternateWorkerId: 'WRK-PILOT-FIRST-OFFICER-BOB'
        );

        $this->assertFalse((bool) $updatedAssignment->assignment_permitted);
        $this->assertTrue((bool) $updatedAssignment->in_progress_safe_handoff_initiated);
        $this->assertEquals('WRK-PILOT-FIRST-OFFICER-BOB', $updatedAssignment->handoff_to_worker_id);
    }

    public function test_campus_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->issueCredential('C-AUD', 'W1', 'HEALTHCARE', true, false);
        $this->service->assignOperationalTask('A-AUD', 'W1', 'C-AUD');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: active assignment with revoked credential
        DB::table('global_personnel_credential_authorizations')->where('credential_code', 'C-AUD')->update([
            'is_revoked' => true,
            'is_active' => false,
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
