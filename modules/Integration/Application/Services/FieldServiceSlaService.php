<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FieldServiceSlaService (Fase 216)
 *
 * Implements:
 *  - 216.1 Field service dispatch with mandatory technician skill & certification verification
 *  - 216.2 Centralized SLA engine with breach detection and automatic penalty credit calculation
 */
class FieldServiceSlaService
{
    /**
     * Register technician with certifications.
     */
    public function registerTechnician(string $code, string $name, string $domain, array $certs): object
    {
        DB::table('ops_fs_technicians')->updateOrInsert(
            ['technician_code' => strtoupper($code)],
            [
                'technician_name' => $name,
                'service_domain' => strtoupper($domain),
                'certifications' => json_encode($certs),
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ops_fs_technicians')->where('technician_code', strtoupper($code))->first();
    }

    /**
     * Dispatch technician to critical task with certification gating.
     */
    public function dispatchTechnician(string $techCode, string $requiredCert, string $orderCode): object
    {
        $tech = DB::table('ops_fs_technicians')->where('technician_code', strtoupper($techCode))->first();
        if (! $tech) {
            throw new \InvalidArgumentException("Technician {$techCode} not found.");
        }

        $certs = json_decode((string) $tech->certifications, true) ?: [];
        if (! in_array(strtoupper($requiredCert), array_map('strtoupper', $certs), true)) {
            throw new \RuntimeException("Dispatch rejected: Technician {$techCode} does not hold required certification {$requiredCert}.");
        }

        $dispatchCode = 'DSP-'.strtoupper(Str::random(8));

        $id = DB::table('ops_fs_dispatches')->insertGetId([
            'dispatch_code' => $dispatchCode,
            'technician_code' => strtoupper($techCode),
            'required_certification' => strtoupper($requiredCert),
            'service_order_code' => strtoupper($orderCode),
            'status' => 'DISPATCHED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_fs_dispatches')->find($id);
    }

    /**
     * Evaluate SLA performance and automatically credit penalty on breach.
     */
    public function evaluateSlaPerformance(string $orderCode, int $targetMinutes, int $actualMinutes, float $penaltyRatePerMinute = 10000.0): object
    {
        $breached = ($actualMinutes > $targetMinutes);
        $penaltyAmount = 0.00;
        $status = 'NONE';

        if ($breached) {
            $overdueMinutes = $actualMinutes - $targetMinutes;
            $penaltyAmount = round($overdueMinutes * $penaltyRatePerMinute, 2);
            $status = 'PENALTY_CREDITED';
        }

        $code = 'SLA-'.strtoupper(Str::random(8));

        $id = DB::table('ops_fs_sla_records')->insertGetId([
            'sla_record_code' => $code,
            'service_order_code' => strtoupper($orderCode),
            'sla_target_minutes' => $targetMinutes,
            'actual_resolution_minutes' => $actualMinutes,
            'is_breached' => $breached,
            'penalty_credit_amount_idr' => $penaltyAmount,
            'penalty_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_fs_sla_records')->find($id);
    }

    /**
     * Quality audit gate (`field:audit`).
     */
    public function audit(): array
    {
        $uncreditedBreaches = DB::table('ops_fs_sla_records')
            ->where('is_breached', true)
            ->where('penalty_status', 'NONE')
            ->count();

        return [
            'status' => $uncreditedBreaches === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_dispatches' => DB::table('ops_fs_dispatches')->count(),
            'total_sla_evaluations' => DB::table('ops_fs_sla_records')->count(),
            'discrepancy_count' => $uncreditedBreaches,
        ];
    }
}
