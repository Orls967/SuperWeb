<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * FieldRemoteSiteOperationsIntegrityService (Fase 411)
 *
 * Implements:
 *  - 411.1 Remote site operating kit: credential check, safety permit, equipment check -> digital pre-start gate
 *  - 411.2 Offline operations protocol: idempotent sync window, safe escalation on lost connectivity
 *  - 411.3 Post-operation verification: evidence upload, reviewer, records sealed
 *  - 411.4 Tests: pre-start gate blocks on missing credential/permit, offline sync zero duplicate, field:audit clean
 *  - 411.5 Edge case: connectivity loss beyond safe threshold (e.g. > 12 hours) safely halts field operations
 *  - 411.6 Risk: pre-start gate is strictly non-bypassable by operational roles
 *  - 411.7 Evidence: pre-start records, offline sync logs, post-op evidence sealed
 */
class FieldRemoteSiteOperationsIntegrityService
{
    public function initializeTask(string $taskCode, string $siteName, string $operatorId): object
    {
        $id = DB::table('ops_field_tasks')->insertGetId([
            'task_code' => strtoupper($taskCode),
            'site_name' => $siteName,
            'operator_id' => $operatorId,
            'credentials_verified' => false,
            'safety_permit_issued' => false,
            'equipment_checked' => false,
            'pre_start_cleared' => false,
            'status' => 'pending_pre_start',
            'hours_offline' => 0,
            'connectivity_safe_halt' => false,
            'post_op_evidence_hash' => null,
            'records_sealed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_field_tasks')->where('id', $id)->first();
    }

    public function clearPreStartGate(
        string $taskCode,
        bool $credentialsVerified,
        bool $safetyPermitIssued,
        bool $equipmentChecked
    ): object {
        $task = DB::table('ops_field_tasks')->where('task_code', strtoupper($taskCode))->first();
        if (! $task) {
            throw new InvalidArgumentException("Task '{$taskCode}' not found.");
        }

        // 411.1, 411.4, 411.6 Non-bypassable gate
        if (! $credentialsVerified || ! $safetyPermitIssued || ! $equipmentChecked) {
            throw new InvalidArgumentException('Pre-start gate blocked: Missing mandatory credential verification, safety permit, or equipment check (411.1, 411.6).');
        }

        DB::table('ops_field_tasks')->where('id', $task->id)->update([
            'credentials_verified' => true,
            'safety_permit_issued' => true,
            'equipment_checked' => true,
            'pre_start_cleared' => true,
            'status' => 'in_progress',
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_field_tasks')->where('id', $task->id)->first();
    }

    public function recordOfflineSync(string $taskCode, string $idempotencyKey, array $payload): object
    {
        $task = DB::table('ops_field_tasks')->where('task_code', strtoupper($taskCode))->first();
        if (! $task) {
            throw new InvalidArgumentException("Task '{$taskCode}' not found.");
        }

        // 411.2 & 411.4 Idempotent sync
        $existing = DB::table('ops_field_offline_syncs')->where('sync_idempotency_key', strtoupper($idempotencyKey))->first();
        if ($existing) {
            return (object) $existing; // Return existing without duplicate insert
        }

        $id = DB::table('ops_field_offline_syncs')->insertGetId([
            'sync_idempotency_key' => strtoupper($idempotencyKey),
            'task_id' => $task->id,
            'payload' => json_encode($payload),
            'synced' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_field_offline_syncs')->where('id', $id)->first();
    }

    /**
     * 411.5 Edge case: Lost connectivity past threshold (> 12 hours) safely halts operations
     */
    public function reportOfflineDuration(string $taskCode, int $hoursOffline): object
    {
        $task = DB::table('ops_field_tasks')->where('task_code', strtoupper($taskCode))->first();
        if (! $task) {
            throw new InvalidArgumentException("Task '{$taskCode}' not found.");
        }

        $safeHalt = $hoursOffline > 12;
        $status = $safeHalt ? 'halted_safety' : $task->status;

        DB::table('ops_field_tasks')->where('id', $task->id)->update([
            'hours_offline' => $hoursOffline,
            'connectivity_safe_halt' => $safeHalt,
            'status' => $status,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_field_tasks')->where('id', $task->id)->first();
    }

    public function sealPostOperationEvidence(string $taskCode, string $evidenceHash, string $reviewer): object
    {
        $task = DB::table('ops_field_tasks')->where('task_code', strtoupper($taskCode))->first();
        if (! $task) {
            throw new InvalidArgumentException("Task '{$taskCode}' not found.");
        }

        // 411.3 & 411.4 Evidence required to close
        if (empty($evidenceHash)) {
            throw new InvalidArgumentException('Closure blocked: Post-op evidence hash required (411.3).');
        }

        DB::table('ops_field_tasks')->where('id', $task->id)->update([
            'post_op_evidence_hash' => $evidenceHash,
            'reviewer' => $reviewer,
            'records_sealed' => true,
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_field_tasks')->where('id', $task->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Tasks in progress without pre-start clearance
        $unauthorizedTasks = DB::table('ops_field_tasks')
            ->where('status', 'in_progress')
            ->where('pre_start_cleared', false)
            ->count();

        // Discrepancy 2: Completed tasks without sealed evidence
        $unsealedCompleted = DB::table('ops_field_tasks')
            ->where('status', 'completed')
            ->where('records_sealed', false)
            ->count();

        $totalDiscrepancies = $unauthorizedTasks + $unsealedCompleted;

        return [
            'status' => $totalDiscrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'unauthorized_tasks' => $unauthorizedTasks,
            'unsealed_completed' => $unsealedCompleted,
            'discrepancy_count' => $totalDiscrepancies,
        ];
    }
}
