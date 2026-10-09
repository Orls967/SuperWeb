<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EducationCredentialAuthorizationService (Fase 384)
 *
 * Implements:
 *  - 384.1 Credential lifecycle authoritative for role eligibility
 *  - 384.4 Tests: Expired/revoked credential blocks new assignment; in-progress task gets safe handoff; campus:audit clean
 *  - 384.5 Edge case: Credential revoked during in-progress task initiates safe handoff and blocks new assignments without safety risk
 *  - 384.6 Risk: Uncertified personnel operating high-risk assets prevented
 */
class EducationCredentialAuthorizationService
{
    /**
     * Issue or register personnel credential (384.1 & 384.4).
     */
    public function issueCredential(
        string $credentialCode,
        string $workerId,
        string $roleDomain,
        bool $isActive = true,
        bool $isRevoked = false,
        ?\DateTimeInterface $expiresAt = null
    ): object {
        $cCode = strtoupper($credentialCode);
        $wId = strtoupper($workerId);

        $id = DB::table('global_personnel_credential_authorizations')->insertGetId([
            'credential_code' => $cCode,
            'worker_id' => $wId,
            'role_domain' => strtoupper($roleDomain),
            'is_active' => $isActive,
            'is_revoked' => $isRevoked,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_personnel_credential_authorizations')->find($id);
    }

    /**
     * Authorize operational task assignment (384.4 & 384.5 Edge Case).
     */
    public function assignOperationalTask(
        string $assignmentCode,
        string $workerId,
        string $credentialCode
    ): object {
        $aCode = strtoupper($assignmentCode);
        $wId = strtoupper($workerId);
        $cCode = strtoupper($credentialCode);

        $credential = DB::table('global_personnel_credential_authorizations')
            ->where('credential_code', $cCode)
            ->first();

        // Core gate 384.4: Expired or revoked credential blocks new assignment
        $isExpired = $credential && $credential->expires_at && now()->greaterThan($credential->expires_at);
        $isInvalid = (! $credential) || (! $credential->is_active) || $credential->is_revoked || $isExpired;

        if ($isInvalid) {
            DB::table('global_operational_task_assignments')->insert([
                'assignment_code' => $aCode,
                'worker_id' => $wId,
                'credential_code' => $cCode,
                'assignment_permitted' => false,
                'in_progress_safe_handoff_initiated' => false,
                'handoff_to_worker_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Operational authorization failure: Credential '{$credentialCode}' is inactive, expired, or revoked (384.4).");
        }

        $id = DB::table('global_operational_task_assignments')->insertGetId([
            'assignment_code' => $aCode,
            'worker_id' => $wId,
            'credential_code' => $cCode,
            'assignment_permitted' => true,
            'in_progress_safe_handoff_initiated' => false,
            'handoff_to_worker_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_operational_task_assignments')->find($id);
    }

    /**
     * Revoke credential during in-progress task, initiating safe handoff (384.4 & 384.5 Edge Case).
     */
    public function revokeCredentialDuringTask(
        string $credentialCode,
        string $assignmentCode,
        string $alternateWorkerId
    ): object {
        $cCode = strtoupper($credentialCode);
        $aCode = strtoupper($assignmentCode);
        $altWorker = strtoupper($alternateWorkerId);

        // Revoke credential
        DB::table('global_personnel_credential_authorizations')
            ->where('credential_code', $cCode)
            ->update([
                'is_active' => false,
                'is_revoked' => true,
                'updated_at' => now(),
            ]);

        // Edge case 384.5: In-progress task receives safe handoff without safety risk
        DB::table('global_operational_task_assignments')
            ->where('assignment_code', $aCode)
            ->update([
                'assignment_permitted' => false,
                'in_progress_safe_handoff_initiated' => true,
                'handoff_to_worker_id' => $altWorker,
                'updated_at' => now(),
            ]);

        return (object) DB::table('global_operational_task_assignments')->where('assignment_code', $aCode)->first();
    }

    /**
     * Campus & Operational Credential Audit (`campus:audit`) (384.4, 384.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Assignments permitted with revoked/inactive credentials
        $invalidActiveAssignments = DB::table('global_operational_task_assignments as a')
            ->join('global_personnel_credential_authorizations as c', 'a.credential_code', '=', 'c.credential_code')
            ->where('a.assignment_permitted', true)
            ->where(function ($query) {
                $query->where('c.is_active', false)
                    ->orWhere('c.is_revoked', true);
            })
            ->count();

        // Discrepancy 2: Revoked assignments without safe handoff initiated
        $abandonedTasks = DB::table('global_operational_task_assignments')
            ->where('assignment_permitted', false)
            ->where('in_progress_safe_handoff_initiated', true)
            ->whereNull('handoff_to_worker_id')
            ->count();

        $discrepancies = $invalidActiveAssignments + $abandonedTasks;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_credentials' => DB::table('global_personnel_credential_authorizations')->count(),
            'total_assignments' => DB::table('global_operational_task_assignments')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
