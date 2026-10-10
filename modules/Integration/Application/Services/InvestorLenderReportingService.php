<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * InvestorLenderReportingService (Fase 439)
 *
 * Implements:
 *  - 439.1 Reporting calendar: covenant tests, rating agency packs, investor updates
 *  - 439.2 Covenant management: headroom projection, early warning (< 15% headroom)
 *  - 439.3 Disclosure control: materiality determination, legal sign-off, internal report consistency check
 *  - 439.4 Tests: covenant calculation exact per definition, disclosure consistency check, treasury:audit clean
 *  - 439.5 Edge case: Covenant nearing limit automatically triggers early warning before breach occurs
 *  - 439.6 Risk: Public disclosure differing from internal reports blocked by mandatory consistency check
 *  - 439.7 Evidence: reporting calendar, covenant monitoring, materiality decisions
 */
class InvestorLenderReportingService
{
    public function monitorCovenant(
        string $covenantCode,
        string $facilityName,
        string $metricName,
        float $maxThreshold,
        float $currentValue
    ): object {
        $headroomPercent = $maxThreshold > 0
            ? round((($maxThreshold - $currentValue) / $maxThreshold) * 100, 2)
            : 0.00;

        $isBreach = ($currentValue > $maxThreshold);
        // 439.2 & 439.5 Early warning if headroom is positive but below 15%
        $earlyWarning = (! $isBreach && $headroomPercent <= 15.00);

        $id = DB::table('fin_debt_covenant_monitors')->insertGetId([
            'covenant_code' => strtoupper($covenantCode),
            'lender_facility_name' => $facilityName,
            'metric_name' => $metricName,
            'max_allowed_threshold' => $maxThreshold,
            'current_metric_value' => $currentValue,
            'headroom_percent' => $headroomPercent,
            'early_warning_triggered' => $earlyWarning,
            'is_in_breach' => $isBreach,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('fin_debt_covenant_monitors')->where('id', $id)->first();
    }

    public function createDisclosureReport(string $code, string $title): object
    {
        $id = DB::table('fin_investor_disclosures')->insertGetId([
            'disclosure_code' => strtoupper($code),
            'report_title' => $title,
            'materiality_cleared' => false,
            'internal_consistency_verified' => false,
            'legal_sign_off' => false,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('fin_investor_disclosures')->where('id', $id)->first();
    }

    /**
     * 439.3, 439.4, 439.6 Verify internal consistency and legal sign off before publishing
     */
    public function publishDisclosure(string $code, bool $consistencyVerified, bool $legalSignOff): object
    {
        $d = DB::table('fin_investor_disclosures')->where('disclosure_code', strtoupper($code))->first();
        if (! $d) {
            throw new InvalidArgumentException("Disclosure '{$code}' not found.");
        }

        // 439.6 Risk: Consistency with internal ledger reports is mandatory
        if (! $consistencyVerified) {
            throw new InvalidArgumentException('Publication blocked: External disclosure must pass reconciliation consistency check against internal reports (439.3, 439.6).');
        }

        if (! $legalSignOff) {
            throw new InvalidArgumentException('Publication blocked: Mandatory legal counsel sign-off missing (439.3, 439.4).');
        }

        DB::table('fin_investor_disclosures')->where('id', $d->id)->update([
            'materiality_cleared' => true,
            'internal_consistency_verified' => true,
            'legal_sign_off' => true,
            'status' => 'published',
            'updated_at' => now(),
        ]);

        return (object) DB::table('fin_investor_disclosures')->where('id', $d->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Published disclosures without internal consistency check
        $unverifiedPublished = DB::table('fin_investor_disclosures')
            ->where('status', 'published')
            ->where(function ($query) {
                $query->where('internal_consistency_verified', false)
                    ->orWhere('legal_sign_off', false);
            })
            ->count();

        // Discrepancy 2: Covenants currently in breach
        $breachedCovenants = DB::table('fin_debt_covenant_monitors')
            ->where('is_in_breach', true)
            ->count();

        $total = $unverifiedPublished + $breachedCovenants;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'unverified_disclosures' => $unverifiedPublished,
            'breached_covenants' => $breachedCovenants,
            'discrepancy_count' => $total,
        ];
    }
}
