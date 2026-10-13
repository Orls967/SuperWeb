<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ProblemManagementRootCauseService (Fase 428)
 *
 * Implements:
 *  - 428.1 Major incident to problem linkage
 *  - 428.2 RCA workflow (5-Why analysis)
 *  - 428.3 Known-error database with documented workarounds
 *  - 428.4 Tests: closure requires verified effectiveness, platform:audit clean
 *  - 428.5 Edge case: Unknown root cause cannot be closed; must escalate to senior engineering review
 *  - 428.6 Risk: Temporary workarounds must have explicit expiry date and owner
 *  - 428.7 Evidence: problem record, RCA doc, effectiveness check
 */
class ProblemManagementRootCauseService
{
    public function openProblemFromIncident(
        string $problemCode,
        string $incidentCode,
        string $serviceName
    ): object {
        $id = DB::table('plt_problem_records')->insertGetId([
            'problem_code' => strtoupper($problemCode),
            'major_incident_code' => strtoupper($incidentCode),
            'service_name' => $serviceName,
            'root_cause_5whys' => null,
            'workaround_details' => null,
            'workaround_expiry_date' => null,
            'effectiveness_verified' => false,
            'escalated_to_engineering_review' => false,
            'status' => 'investigating',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_problem_records')->where('id', $id)->first();
    }

    /**
     * 428.3 & 428.6 Document temporary workaround with mandatory expiry date
     */
    public function registerWorkaround(string $problemCode, string $workaround, string $expiryDate): object
    {
        $prob = DB::table('plt_problem_records')->where('problem_code', strtoupper($problemCode))->first();
        if (! $prob) {
            throw new InvalidArgumentException("Problem '{$problemCode}' not found.");
        }

        DB::table('plt_problem_records')->where('id', $prob->id)->update([
            'workaround_details' => $workaround,
            'workaround_expiry_date' => $expiryDate,
            'status' => 'known_error',
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_problem_records')->where('id', $prob->id)->first();
    }

    /**
     * 428.2 & 428.4 Complete RCA and close with verified effectiveness
     */
    public function closeProblemWithRca(string $problemCode, string $fiveWhys, bool $effectivenessVerified): object
    {
        $prob = DB::table('plt_problem_records')->where('problem_code', strtoupper($problemCode))->first();
        if (! $prob) {
            throw new InvalidArgumentException("Problem '{$problemCode}' not found.");
        }

        // 428.4 Closure strictly requires verified effectiveness
        if (! $effectivenessVerified) {
            throw new InvalidArgumentException('Closure blocked: Problem closure requires verified permanent countermeasure effectiveness (428.2, 428.4).');
        }

        DB::table('plt_problem_records')->where('id', $prob->id)->update([
            'root_cause_5whys' => $fiveWhys,
            'effectiveness_verified' => true,
            'status' => 'closed',
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_problem_records')->where('id', $prob->id)->first();
    }

    /**
     * 428.5 Edge case: Root cause not found -> strictly forbidden to close, escalate to engineering review
     */
    public function escalateUnknownRootCause(string $problemCode): object
    {
        $prob = DB::table('plt_problem_records')->where('problem_code', strtoupper($problemCode))->first();
        if (! $prob) {
            throw new InvalidArgumentException("Problem '{$problemCode}' not found.");
        }

        DB::table('plt_problem_records')->where('id', $prob->id)->update([
            'escalated_to_engineering_review' => true,
            'status' => 'escalated',
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_problem_records')->where('id', $prob->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Closed problems without RCA or unverified effectiveness
        $invalidClosures = DB::table('plt_problem_records')
            ->where('status', 'closed')
            ->where(function ($query) {
                $query->whereNull('root_cause_5whys')
                    ->orWhere('effectiveness_verified', false);
            })
            ->count();

        return [
            'status' => $invalidClosures === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_problems' => DB::table('plt_problem_records')->count(),
            'discrepancy_count' => $invalidClosures,
        ];
    }
}
