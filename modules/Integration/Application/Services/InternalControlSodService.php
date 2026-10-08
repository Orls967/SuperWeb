<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * InternalControlSodService (Fase 203)
 *
 * Implements:
 *  - 203.2 Segregation of Duties (SoD) conflict detection across all lines
 *  - 203.4 Segregation of privileged access and break-glass emergency procedure logging
 */
class InternalControlSodService
{
    /**
     * Incompatible role pairs per domain.
     */
    protected array $incompatiblePairs = [
        ['PO_CREATOR', 'PO_APPROVER'],
        ['PAYMENT_INITIATOR', 'PAYMENT_RELEASER'],
        ['CLAIM_ADJUSTER', 'CLAIM_PAYER'],
        ['SYSTEM_ADMIN', 'TRANSACTION_APPROVER'],
    ];

    /**
     * Check user role assignments for SoD conflicts.
     */
    public function checkUserSod(string $userId, string $domain, array $assignedRoles): object
    {
        $hasConflict = false;
        $conflictDetail = null;

        foreach ($this->incompatiblePairs as $pair) {
            if (in_array($pair[0], $assignedRoles, true) && in_array($pair[1], $assignedRoles, true)) {
                $hasConflict = true;
                $conflictDetail = "SoD Conflict: Cannot hold both {$pair[0]} and {$pair[1]}.";
                break;
            }
        }

        $id = DB::table('erm_sod_conflict_checks')->insertGetId([
            'user_id' => $userId,
            'domain_code' => strtoupper($domain),
            'assigned_roles' => json_encode($assignedRoles),
            'has_sod_conflict' => $hasConflict,
            'conflicting_roles_detail' => $conflictDetail,
            'remediation_status' => $hasConflict ? 'UNRESOLVED' : 'RESOLVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('erm_sod_conflict_checks')->find($id);
    }

    /**
     * Log emergency break-glass procedure.
     */
    public function recordBreakGlassAccess(string $adminId, string $action, string $justification): object
    {
        if (empty(trim($justification))) {
            throw new \InvalidArgumentException('Break-glass procedure requires emergency justification.');
        }

        $code = 'BG-'.strtoupper(Str::random(8));

        $id = DB::table('erm_breakglass_logs')->insertGetId([
            'log_code' => $code,
            'admin_user_id' => $adminId,
            'emergency_action' => strtoupper($action),
            'emergency_justification' => $justification,
            'audited_by_compliance' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('erm_breakglass_logs')->find($id);
    }

    /**
     * Quality audit gate (`enterprise:audit`).
     */
    public function audit(): array
    {
        $unresolvedConflicts = DB::table('erm_sod_conflict_checks')
            ->where('has_sod_conflict', true)
            ->where('remediation_status', 'UNRESOLVED')
            ->count();

        return [
            'status' => $unresolvedConflicts === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_sod_checks' => DB::table('erm_sod_conflict_checks')->count(),
            'total_breakglass_logs' => DB::table('erm_breakglass_logs')->count(),
            'discrepancy_count' => $unresolvedConflicts,
        ];
    }
}
