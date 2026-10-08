<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NatureFinanceService (Fase 173 — Lini 23)
 *
 * Implements:
 *  - 173.1 Nature project registry & credit issuance bounded by verified outcomes
 *  - 173.2 Credit retirement ledger preventing resale or double retirement
 *  - 173.3 Corporate nature-positive benefit sharing strictly summing to total proceeds
 */
class NatureFinanceService
{
    /**
     * Register nature/biodiversity project.
     */
    public function registerProject(string $name, string $methodology, float $verifiedOutcomes): object
    {
        $code = 'NAT-PRJ-'.strtoupper(Str::random(8));

        $id = DB::table('for_nature_projects')->insertGetId([
            'project_code' => $code,
            'project_name' => $name,
            'methodology_version' => $methodology,
            'verified_outcome_units' => $verifiedOutcomes,
            'issued_credits_count' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('for_nature_projects')->find($id);
    }

    /**
     * Issue nature credits bounded by verified outcome units.
     */
    public function issueCredits(string $projectCode, float $creditCount, string $holder): array
    {
        $project = DB::table('for_nature_projects')->where('project_code', $projectCode)->first();
        if (! $project) {
            throw new \InvalidArgumentException("Project {$projectCode} not found.");
        }

        $remaining = (float) $project->verified_outcome_units - (float) $project->issued_credits_count;
        if ($creditCount > $remaining) {
            throw new \RuntimeException("Credit issuance rejected: {$creditCount} credits requested exceeds remaining verified outcomes ({$remaining}).");
        }

        $serials = [];
        for ($i = 0; $i < (int) $creditCount; $i++) {
            $serial = 'NAT-CRD-'.strtoupper(Str::random(10));
            DB::table('for_nature_credits')->insert([
                'credit_serial_number' => $serial,
                'project_code' => $projectCode,
                'current_holder' => $holder,
                'is_retired' => false,
                'retired_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $serials[] = $serial;
        }

        DB::table('for_nature_projects')->where('project_code', $projectCode)->update([
            'issued_credits_count' => DB::raw("issued_credits_count + {$creditCount}"),
            'updated_at' => now(),
        ]);

        return $serials;
    }

    /**
     * Retire nature credit to prevent resale.
     */
    public function retireCredit(string $serialNumber): object
    {
        $credit = DB::table('for_nature_credits')->where('credit_serial_number', $serialNumber)->first();
        if (! $credit) {
            throw new \InvalidArgumentException("Credit {$serialNumber} not found.");
        }

        if ((bool) $credit->is_retired) {
            throw new \RuntimeException("Credit {$serialNumber} has already been retired and cannot be resold or re-retired.");
        }

        DB::table('for_nature_credits')->where('credit_serial_number', $serialNumber)->update([
            'is_retired' => true,
            'retired_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('for_nature_credits')->where('credit_serial_number', $serialNumber)->first();
    }

    /**
     * Calculate community benefit share from carbon/nature credit proceeds.
     * Invarian: community_disbursement + developer_share = total_proceeds.
     */
    public function distributeBenefitShare(string $projectCode, float $proceedsIdr, float $communityPct = 30.0): object
    {
        $communityAmount = round($proceedsIdr * ($communityPct / 100.0), 2);
        $developerAmount = round($proceedsIdr - $communityAmount, 2);
        $sums = round($communityAmount + $developerAmount, 2) === round($proceedsIdr, 2);

        $shareCode = 'SHR-'.strtoupper(Str::random(8));

        $id = DB::table('for_community_benefit_shares')->insertGetId([
            'share_code' => $shareCode,
            'project_code' => $projectCode,
            'total_proceeds_idr' => $proceedsIdr,
            'community_share_pct' => $communityPct,
            'community_disbursement_idr' => $communityAmount,
            'developer_share_idr' => $developerAmount,
            'math_sums_to_proceeds' => $sums,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('for_community_benefit_shares')->find($id);
    }

    /**
     * Quality audit gate (`nature:audit`).
     */
    public function audit(): array
    {
        $overIssued = DB::table('for_nature_projects')
            ->whereRaw('issued_credits_count > verified_outcome_units')
            ->count();

        $unbalancedShares = DB::table('for_community_benefit_shares')
            ->where('math_sums_to_proceeds', false)
            ->count();

        $discrepancies = $overIssued + $unbalancedShares;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_projects' => DB::table('for_nature_projects')->count(),
            'total_credits' => DB::table('for_nature_credits')->count(),
            'total_benefit_shares' => DB::table('for_community_benefit_shares')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
