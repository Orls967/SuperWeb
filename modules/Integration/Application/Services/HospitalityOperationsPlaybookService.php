<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * HospitalityOperationsPlaybookService (Fase 280)
 *
 * Implements:
 *  - 280.1 Multi-outlet operations bible across restaurant, hotel and convention venues with immutable versioning
 *  - 280.2 Shift playbook engine requiring mandatory training attestation prior to independent shift assignment
 *  - 280.3 Mystery guest quality audit with deterministic formulaic grading
 *  - 280.5 Edge case: Poor mystery audit grades (Grade C/D, composite < 70) trigger mandatory action plan & brand scorecard downgrade
 */
class HospitalityOperationsPlaybookService
{
    /**
     * Register versioned SOP in operations bible (280.1 & 280.4).
     */
    public function registerSop(
        string $sopCode,
        string $venueType,
        string $title,
        string $version = 'V1.0',
        string $lang = 'ID'
    ): object {
        $code = strtoupper($sopCode);

        $id = DB::table('hospitality_operations_sop_bible')->insertGetId([
            'sop_code' => $code,
            'venue_type' => strtoupper($venueType),
            'title' => $title,
            'version' => $version,
            'is_active_version_immutable' => true, // 280.4
            'language_code' => strtoupper($lang),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hospitality_operations_sop_bible')->find($id);
    }

    /**
     * Attest worker completion of SOP training (280.1 & 280.4).
     */
    public function attestWorkerTraining(string $workerId, string $sopCode): object
    {
        $wId = strtoupper($workerId);
        $sCode = strtoupper($sopCode);

        $id = DB::table('hospitality_worker_attestations')->insertGetId([
            'worker_id' => $wId,
            'sop_code' => $sCode,
            'is_training_attested' => true,
            'attestation_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hospitality_worker_attestations')->find($id);
    }

    /**
     * Assign worker to shift enforcing mandatory training attestation (280.2 & 280.4).
     */
    public function assignShift(
        string $shiftCode,
        string $outletCode,
        string $shiftType,
        string $workerId,
        string $requiredSopCode
    ): object {
        $sCode = strtoupper($shiftCode);
        $wId = strtoupper($workerId);
        $reqSop = strtoupper($requiredSopCode);

        // Mandatory attestation check (280.4): Cannot work shift without attested training
        $attested = DB::table('hospitality_worker_attestations')
            ->where('worker_id', $wId)
            ->where('sop_code', $reqSop)
            ->where('is_training_attested', true)
            ->exists();

        if (! $attested) {
            throw new InvalidArgumentException("Shift assignment blocked: Worker '{$workerId}' has not completed mandatory training attestation for SOP '{$requiredSopCode}' (280.4).");
        }

        $id = DB::table('hospitality_shift_playbooks')->insertGetId([
            'shift_code' => $sCode,
            'outlet_code' => strtoupper($outletCode),
            'shift_type' => strtoupper($shiftType),
            'assigned_worker_id' => $wId,
            'worker_authorized' => true,
            'completion_evidence_submitted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hospitality_shift_playbooks')->find($id);
    }

    /**
     * Record mystery guest audit with formulaic grade & action plan trigger (280.3 & 280.5 Edge Case).
     */
    public function recordMysteryAudit(
        string $auditCode,
        string $outletCode,
        float $serviceScore,
        float $cleanlinessScore,
        float $foodQualityScore
    ): object {
        $code = strtoupper($auditCode);

        // Formulaic composite score (weight: 35% service, 35% cleanliness, 30% food quality)
        $composite = round(($serviceScore * 0.35) + ($cleanlinessScore * 0.35) + ($foodQualityScore * 0.30), 2);

        // Deterministic grade: >= 90 => A, >= 80 => B, >= 70 => C, < 70 => D (280.4)
        if ($composite >= 90.0) {
            $grade = 'A';
        } elseif ($composite >= 80.0) {
            $grade = 'B';
        } elseif ($composite >= 70.0) {
            $grade = 'C';
        } else {
            $grade = 'D';
        }

        // Edge case 280.5: Poor grade (C or D, composite < 80) requires action plan and brand scorecard penalty
        $actionPlanRequired = ($composite < 80.0);
        $brandImpacted = ($composite < 70.0);

        $id = DB::table('hospitality_mystery_guest_audits')->insertGetId([
            'audit_code' => $code,
            'outlet_code' => strtoupper($outletCode),
            'service_score' => $serviceScore,
            'cleanliness_score' => $cleanlinessScore,
            'food_quality_score' => $foodQualityScore,
            'composite_score' => $composite,
            'outlet_grade' => $grade,
            'action_plan_required' => $actionPlanRequired,
            'brand_scorecard_impacted' => $brandImpacted,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hospitality_mystery_guest_audits')->find($id);
    }

    /**
     * Hospitality & Venue Operations Platform Audit (`quality:audit`) (280.4, 280.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Unauthorized workers assigned to shift
        $unauthorizedShifts = DB::table('hospitality_shift_playbooks')
            ->where('worker_authorized', false)
            ->count();

        // Discrepancy 2: Grade D audits without brand scorecard impact
        $unaddressedBadAudits = DB::table('hospitality_mystery_guest_audits')
            ->where('outlet_grade', 'D')
            ->where('brand_scorecard_impacted', false)
            ->count();

        // Discrepancy 3: Mutable SOP versions
        $mutableSops = DB::table('hospitality_operations_sop_bible')
            ->where('is_active_version_immutable', false)
            ->count();

        $discrepancies = $unauthorizedShifts + $unaddressedBadAudits + $mutableSops;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_sops' => DB::table('hospitality_operations_sop_bible')->count(),
            'total_attestations' => DB::table('hospitality_worker_attestations')->count(),
            'total_shifts' => DB::table('hospitality_shift_playbooks')->count(),
            'total_mystery_audits' => DB::table('hospitality_mystery_guest_audits')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
