<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * QualityManagementSystemService (Fase 213)
 *
 * Implements:
 *  - 213.2 Unified Nonconformance & CAPA tracking with automated overdue escalation
 *  - 213.4 Customer complaint resolution with mandatory ledger credit linkage for monetary compensation
 */
class QualityManagementSystemService
{
    /**
     * File nonconformance and establish CAPA.
     */
    public function createCapa(string $domain, string $standard, string $description, string $rootCause, string $actionPlan, Carbon $dueDate): object
    {
        $code = 'CAPA-'.strtoupper(Str::random(8));

        $id = DB::table('ops_qms_capa_records')->insertGetId([
            'capa_code' => $code,
            'domain_code' => strtoupper($domain),
            'standard_name' => strtoupper($standard),
            'nonconformance_description' => $description,
            'root_cause_analysis' => $rootCause,
            'corrective_action_plan' => $actionPlan,
            'target_completion_date' => $dueDate,
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_qms_capa_records')->find($id);
    }

    /**
     * Verify and close CAPA, or escalate if target date passed.
     */
    public function verifyCapaCompletion(string $capaCode, bool $actionEffective): object
    {
        $capa = DB::table('ops_qms_capa_records')->where('capa_code', $capaCode)->first();
        if (! $capa) {
            throw new \InvalidArgumentException("CAPA {$capaCode} not found.");
        }

        $now = Carbon::now();
        $dueDate = Carbon::parse($capa->target_completion_date);

        if ($actionEffective) {
            $status = 'VERIFIED_CLOSED';
        } else {
            $status = $now->isAfter($dueDate) ? 'OVERDUE_ESCALATED' : 'OPEN';
        }

        DB::table('ops_qms_capa_records')->where('capa_code', $capaCode)->update([
            'status' => $status,
            'updated_at' => $now,
        ]);

        return (object) DB::table('ops_qms_capa_records')->where('capa_code', $capaCode)->first();
    }

    /**
     * Resolve customer complaint with verified ledger link when compensation is granted.
     */
    public function resolveComplaint(string $channel, string $product, float $compensationAmount, ?string $ledgerCreditRef = null): object
    {
        if ($compensationAmount > 0.0 && empty($ledgerCreditRef)) {
            throw new \InvalidArgumentException('Complaint resolution requirement: Monetary compensation requires a valid ledger credit reference.');
        }

        $code = 'CMP-'.strtoupper(Str::random(8));

        $id = DB::table('ops_qms_complaints')->insertGetId([
            'complaint_code' => $code,
            'channel' => strtoupper($channel),
            'affected_service_or_product' => $product,
            'compensation_amount_idr' => $compensationAmount,
            'ledger_credit_reference' => $ledgerCreditRef,
            'resolution_status' => 'RESOLVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_qms_complaints')->find($id);
    }

    /**
     * Quality audit gate (`quality:audit`).
     */
    public function audit(): array
    {
        $unlinkedCompensations = DB::table('ops_qms_complaints')
            ->where('compensation_amount_idr', '>', 0.0)
            ->whereNull('ledger_credit_reference')
            ->count();

        return [
            'status' => $unlinkedCompensations === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_capas' => DB::table('ops_qms_capa_records')->count(),
            'total_complaints' => DB::table('ops_qms_complaints')->count(),
            'discrepancy_count' => $unlinkedCompensations,
        ];
    }
}
