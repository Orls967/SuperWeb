<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GroupCommandCenterService (Fase 190)
 *
 * Implements:
 *  - 190.2 Exception triage with deduplication fingerprint & SLA escalation
 *  - 190.3 Daily cross-line end-of-day close reconciling operational revenue to ledger (Σ variance = 0)
 */
class GroupCommandCenterService
{
    /**
     * Triage operational alert across 30 lines. Deduplicates based on fingerprint.
     */
    public function triageAlert(string $fingerprint, string $lineCode, string $category, string $ownerRole, int $slaMinutes = 30): object
    {
        $existing = DB::table('cmd_operational_alerts')->where('dedup_fingerprint', $fingerprint)->first();
        if ($existing) {
            return (object) $existing; // Dedup: merges duplicate alerts
        }

        $code = 'ALT-CMD-'.strtoupper(Str::random(8));

        $id = DB::table('cmd_operational_alerts')->insertGetId([
            'alert_code' => $code,
            'dedup_fingerprint' => $fingerprint,
            'line_code' => strtoupper($lineCode),
            'category' => strtoupper($category),
            'assigned_owner_role' => strtoupper($ownerRole),
            'sla_response_minutes' => $slaMinutes,
            'escalation_level' => 'LEVEL_1',
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('cmd_operational_alerts')->find($id);
    }

    /**
     * Escalate alert if SLA breached.
     */
    public function escalateAlert(string $alertCode): object
    {
        DB::table('cmd_operational_alerts')->where('alert_code', $alertCode)->update([
            'escalation_level' => 'LEVEL_2_DIRECTOR',
            'status' => 'ESCALATED',
            'updated_at' => now(),
        ]);

        return (object) DB::table('cmd_operational_alerts')->where('alert_code', $alertCode)->first();
    }

    /**
     * Execute end-of-day close for a business vertical.
     * Enforces double-entry ledger parity: operational revenue must match posted ledger (variance == 0).
     */
    public function executeDailyClose(Carbon $date, string $lineCode, float $operationalRev, float $postedLedger): object
    {
        $variance = round($operationalRev - $postedLedger, 2);
        if ($variance !== 0.0) {
            throw new \RuntimeException("EOD close discrepancy: Operational revenue ({$operationalRev}) does not match general ledger ({$postedLedger}). Discrepancy: {$variance}.");
        }

        $code = 'EOD-'.strtoupper($lineCode).'-'.$date->format('Ymd');

        $id = DB::table('cmd_daily_eod_closes')->insertGetId([
            'close_batch_code' => $code,
            'close_date' => $date->toDateString(),
            'line_code' => strtoupper($lineCode),
            'total_revenue_idr' => $operationalRev,
            'posted_ledger_idr' => $postedLedger,
            'eod_variance_idr' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('cmd_daily_eod_closes')->find($id);
    }

    /**
     * Quality audit gate (`commandcenter:audit`).
     */
    public function audit(): array
    {
        $variances = DB::table('cmd_daily_eod_closes')
            ->where('eod_variance_idr', '!=', 0.0)
            ->count();

        return [
            'status' => $variances === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_alerts' => DB::table('cmd_operational_alerts')->count(),
            'total_eod_closes' => DB::table('cmd_daily_eod_closes')->count(),
            'discrepancy_count' => $variances,
        ];
    }
}
