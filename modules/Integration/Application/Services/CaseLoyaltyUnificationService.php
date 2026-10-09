<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CaseLoyaltyUnificationService (Fase 220)
 *
 * Implements:
 *  - 220.2 Cross-line case orchestration where parent case closure requires all subcases to be resolved
 *  - 220.3 Unified loyalty points ledger across 30 lines with point conservation checks
 */
class CaseLoyaltyUnificationService
{
    /**
     * Create parent case and linked subcases across business lines.
     */
    public function createMultiLineCase(string $goldenId, string $summary, array $lines): object
    {
        $caseCode = 'CASE-'.strtoupper(Str::random(8));

        DB::table('crm_cross_line_cases')->insert([
            'case_code' => $caseCode,
            'customer_golden_id' => strtoupper($goldenId),
            'issue_summary' => $summary,
            'status' => 'IN_PROGRESS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($lines as $line) {
            $subCode = 'SUB-'.strtoupper(Str::random(8));
            DB::table('crm_cross_line_subcases')->insert([
                'subcase_code' => $subCode,
                'case_code' => $caseCode,
                'business_line' => strtoupper($line),
                'status' => 'OPEN',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (object) DB::table('crm_cross_line_cases')->where('case_code', $caseCode)->first();
    }

    /**
     * Resolve individual subcase.
     */
    public function resolveSubcase(string $subcaseCode): void
    {
        DB::table('crm_cross_line_subcases')->where('subcase_code', strtoupper($subcaseCode))->update([
            'status' => 'RESOLVED',
            'updated_at' => now(),
        ]);
    }

    /**
     * Close parent case, verifying all subcases are strictly resolved.
     */
    public function closeParentCase(string $caseCode): object
    {
        $openCount = DB::table('crm_cross_line_subcases')
            ->where('case_code', strtoupper($caseCode))
            ->where('status', 'OPEN')
            ->count();

        if ($openCount > 0) {
            throw new \RuntimeException("Case closure error: Cannot close parent case {$caseCode} while {$openCount} subcases remain unresolved.");
        }

        DB::table('crm_cross_line_cases')->where('case_code', strtoupper($caseCode))->update([
            'status' => 'RESOLVED_CLOSED',
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_cross_line_cases')->where('case_code', strtoupper($caseCode))->first();
    }

    /**
     * Transact loyalty points (earn or redeem).
     */
    public function recordLoyaltyTransaction(string $goldenId, string $line, int $earned, int $redeemed): object
    {
        $currentBalance = (int) DB::table('crm_loyalty_point_ledgers')
            ->where('customer_golden_id', strtoupper($goldenId))
            ->sum(DB::raw('earned_points - redeemed_points'));

        if ($redeemed > 0 && ($currentBalance + $earned) < $redeemed) {
            throw new \InvalidArgumentException("Loyalty redemption rejected: Requested {$redeemed} points exceeds balance {$currentBalance}.");
        }

        $code = 'LP-'.strtoupper(Str::random(8));

        $id = DB::table('crm_loyalty_point_ledgers')->insertGetId([
            'tx_code' => $code,
            'customer_golden_id' => strtoupper($goldenId),
            'business_line' => strtoupper($line),
            'earned_points' => $earned,
            'redeemed_points' => $redeemed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_loyalty_point_ledgers')->find($id);
    }

    /**
     * Quality audit gate (`crm:audit`).
     */
    public function audit(): array
    {
        // Prematurely closed cases with open subcases
        $inconsistentCases = DB::table('crm_cross_line_cases')
            ->where('crm_cross_line_cases.status', 'RESOLVED_CLOSED')
            ->join('crm_cross_line_subcases', 'crm_cross_line_cases.case_code', '=', 'crm_cross_line_subcases.case_code')
            ->where('crm_cross_line_subcases.status', 'OPEN')
            ->count();

        return [
            'status' => $inconsistentCases === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_parent_cases' => DB::table('crm_cross_line_cases')->count(),
            'total_subcases' => DB::table('crm_cross_line_subcases')->count(),
            'total_loyalty_transactions' => DB::table('crm_loyalty_point_ledgers')->count(),
            'discrepancy_count' => $inconsistentCases,
        ];
    }
}
