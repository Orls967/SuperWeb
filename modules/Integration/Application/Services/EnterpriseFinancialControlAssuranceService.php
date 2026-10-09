<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseFinancialControlAssuranceService (Fase 458)
 *
 * Implements:
 *  - 458.1 Consolidated control self-assessment across finance processes with management assertion
 *  - 458.2 Independent assurance coverage plan: no material process untested
 *  - 458.3 Deficiency aggregation (significant deficiency / material weakness) & disclosure consideration
 *  - 458.4 Tests: coverage map complete, assertion signed, enterprise:audit clean
 *  - 458.5 Edge case: Material finance process without assurance blocks management assertion sign-off
 *  - 458.6 Risk: Aggregated deficiencies require mandatory documented disclosure consideration
 *  - 458.7 Evidence: assertion sign-off, coverage map, deficiency analysis
 */
class EnterpriseFinancialControlAssuranceService
{
    public function registerProcess(string $code, string $title, bool $isMaterial = true): object
    {
        $id = DB::table('int_financial_control_processes')->insertGetId([
            'process_code' => strtoupper($code),
            'process_title' => $title,
            'is_material_process' => $isMaterial,
            'assurance_tested' => false,
            'management_assertion_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_financial_control_processes')->where('id', $id)->first();
    }

    public function recordAssuranceTesting(string $code, bool $tested): object
    {
        $p = DB::table('int_financial_control_processes')->where('process_code', strtoupper($code))->first();
        if (! $p) {
            throw new InvalidArgumentException("Process '{$code}' not found.");
        }

        DB::table('int_financial_control_processes')->where('id', $p->id)->update([
            'assurance_tested' => $tested,
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_financial_control_processes')->where('id', $p->id)->first();
    }

    /**
     * 458.1, 458.4, 458.5 Sign management assertion on internal control over financial reporting
     */
    public function signManagementAssertion(string $code): object
    {
        $p = DB::table('int_financial_control_processes')->where('process_code', strtoupper($code))->first();
        if (! $p) {
            throw new InvalidArgumentException("Process '{$code}' not found.");
        }

        // 458.5 Edge case: Untested material process blocks assertion sign-off
        if ($p->is_material_process && ! $p->assurance_tested) {
            throw new InvalidArgumentException("Assertion blocked: Material finance process '{$code}' has not undergone independent assurance testing (458.2, 458.5).");
        }

        DB::table('int_financial_control_processes')->where('id', $p->id)->update([
            'management_assertion_status' => 'signed_effective',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_financial_control_processes')->where('id', $p->id)->first();
    }

    /**
     * 458.3 & 458.6 Log deficiency and enforce disclosure consideration
     */
    public function logDeficiency(string $defCode, string $processCode, string $severity, bool $disclosureConsidered): object
    {
        $sev = strtolower($severity);

        // 458.6 Risk: Significant deficiency and material weakness require disclosure consideration
        if (in_array($sev, ['significant_deficiency', 'material_weakness'], true) && ! $disclosureConsidered) {
            throw new InvalidArgumentException("Deficiency logging blocked: Severe deficiency ({$sev}) requires mandatory documented disclosure consideration (458.3, 458.6).");
        }

        $id = DB::table('int_control_deficiencies')->insertGetId([
            'deficiency_code' => strtoupper($defCode),
            'process_code' => strtoupper($processCode),
            'severity' => $sev,
            'disclosure_consideration_documented' => $disclosureConsidered,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_control_deficiencies')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Material processes signed effective without assurance testing
        $untestedSigned = DB::table('int_financial_control_processes')
            ->where('is_material_process', true)
            ->where('management_assertion_status', 'signed_effective')
            ->where('assurance_tested', false)
            ->count();

        // Discrepancy 2: Material weaknesses without disclosure documentation
        $undisclosedWeakness = DB::table('int_control_deficiencies')
            ->where('severity', 'material_weakness')
            ->where('disclosure_consideration_documented', false)
            ->count();

        $total = $untestedSigned + $undisclosedWeakness;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_processes' => DB::table('int_financial_control_processes')->count(),
            'total_deficiencies' => DB::table('int_control_deficiencies')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
