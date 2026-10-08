<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RndTechTransferService (Fase 218)
 *
 * Implements:
 *  - 218.1 Stage-gate innovation lifecycle where stages cannot be skipped without formal signoff
 *  - 218.2 IP portfolio governance with mandatory freedom-to-operate (FTO) clearance
 */
class RndTechTransferService
{
    /**
     * Initialize stage gate ladder for R&D project.
     */
    public function initProjectStages(string $projectCode): void
    {
        $stages = [
            1 => 'DISCOVERY',
            2 => 'LAB_EXPERIMENT',
            3 => 'PILOT_LINE',
            4 => 'MASS_RELEASE',
        ];

        foreach ($stages as $order => $name) {
            DB::table('ops_rnd_stage_gates')->updateOrInsert(
                ['project_code' => strtoupper($projectCode), 'stage_name' => $name],
                [
                    'stage_order' => $order,
                    'is_signed_off' => false,
                    'signed_off_by' => null,
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Sign off stage gate ensuring previous sequential gates are all signed off.
     */
    public function signOffStageGate(string $projectCode, string $stageName, string $officer): object
    {
        $target = DB::table('ops_rnd_stage_gates')
            ->where('project_code', strtoupper($projectCode))
            ->where('stage_name', strtoupper($stageName))
            ->first();

        if (! $target) {
            throw new \InvalidArgumentException("Stage gate {$stageName} not found for project {$projectCode}.");
        }

        // Check all preceding stages
        $precedingUnapproved = DB::table('ops_rnd_stage_gates')
            ->where('project_code', strtoupper($projectCode))
            ->where('stage_order', '<', (int) $target->stage_order)
            ->where('is_signed_off', false)
            ->count();

        if ($precedingUnapproved > 0) {
            throw new \RuntimeException("Stage gate violation: Cannot sign off {$stageName} before all preceding stage gates are completed.");
        }

        DB::table('ops_rnd_stage_gates')
            ->where('id', $target->id)
            ->update([
                'is_signed_off' => true,
                'signed_off_by' => $officer,
                'updated_at' => now(),
            ]);

        return (object) DB::table('ops_rnd_stage_gates')->where('id', $target->id)->first();
    }

    /**
     * Register IP asset with FTO status and renewal deadline.
     */
    public function registerIpAsset(string $ipCode, string $title, string $type, string $ftoStatus, string $renewalDate): object
    {
        DB::table('ops_rnd_ip_assets')->updateOrInsert(
            ['ip_code' => strtoupper($ipCode)],
            [
                'title' => $title,
                'ip_type' => strtoupper($type),
                'fto_status' => strtoupper($ftoStatus),
                'renewal_deadline' => $renewalDate,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ops_rnd_ip_assets')->where('ip_code', strtoupper($ipCode))->first();
    }

    /**
     * Quality audit gate (`plm:audit`).
     */
    public function audit(): array
    {
        // IP with expired deadlines or infringing risks
        $today = Carbon::today()->toDateString();
        $criticalIpRisks = DB::table('ops_rnd_ip_assets')
            ->where('fto_status', 'INFRINGING_RISK')
            ->orWhere('renewal_deadline', '<', $today)
            ->count();

        return [
            'status' => $criticalIpRisks === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_stage_gates' => DB::table('ops_rnd_stage_gates')->count(),
            'total_ip_assets' => DB::table('ops_rnd_ip_assets')->count(),
            'discrepancy_count' => $criticalIpRisks,
        ];
    }
}
