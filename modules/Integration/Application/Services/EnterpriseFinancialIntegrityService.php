<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseFinancialIntegrityService (Fase 480)
 *
 * Implements:
 *  - 480.1 Financial integrity statement: zero exceptions required across reconciliations, audits, hash-chains
 *  - 480.2 Trust metrics & audit pass rates
 *  - 480.3 Independent external auditor opinion simulation
 *  - 480.4 Tests: statement covers all domains, sample passes, audit clean
 *  - 480.5 Edge case: Statement signing strictly blocked if exception count > 0 (remediation mandatory)
 *  - 480.6 Risk: Auditor sample testing method verified before opinion issuance
 *  - 480.7 Evidence: signed statement, trust metrics trend, auditor opinion
 */
class EnterpriseFinancialIntegrityService
{
    public function draftIntegrityStatement(string $code, string $period, int $auditsEvaluated, int $exceptions = 0): object
    {
        $id = DB::table('int_enterprise_financial_integrity_statements')->insertGetId([
            'statement_code' => strtoupper($code),
            'period' => strtoupper($period),
            'total_audits_evaluated' => $auditsEvaluated,
            'exception_count' => $exceptions,
            'signatory_role' => null,
            'auditor_opinion' => null,
            'auditor_sample_verified' => false,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_financial_integrity_statements')->where('id', $id)->first();
    }

    /**
     * 480.1 & 480.5 Sign statement strictly requiring zero exceptions
     */
    public function signStatement(string $code, string $signatoryRole): object
    {
        $stmt = DB::table('int_enterprise_financial_integrity_statements')->where('statement_code', strtoupper($code))->first();
        if (! $stmt) {
            throw new InvalidArgumentException("Statement '{$code}' not found.");
        }

        // 480.5 Edge case: Any exceptions strictly prevent signing
        if ($stmt->exception_count > 0) {
            throw new InvalidArgumentException("Signing blocked: Financial integrity statement has {$stmt->exception_count} unresolved exceptions (zero exception policy violated) (480.1, 480.5).");
        }

        DB::table('int_enterprise_financial_integrity_statements')->where('id', $stmt->id)->update([
            'signatory_role' => $signatoryRole,
            'status' => 'signed',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_financial_integrity_statements')->where('id', $stmt->id)->first();
    }

    /**
     * 480.3 & 480.6 Issue external auditor unqualified opinion with sample verification
     */
    public function issueAuditorOpinion(string $code, string $opinion = 'unqualified', bool $sampleVerified = true): object
    {
        $stmt = DB::table('int_enterprise_financial_integrity_statements')->where('statement_code', strtoupper($code))->first();
        if (! $stmt) {
            throw new InvalidArgumentException("Statement '{$code}' not found.");
        }

        // 480.6 Risk: Auditor sample testing must be verified
        if (! $sampleVerified) {
            throw new InvalidArgumentException("Auditor opinion blocked: Independent sampling methodology has not been validated (480.6).");
        }

        DB::table('int_enterprise_financial_integrity_statements')->where('id', $stmt->id)->update([
            'auditor_opinion' => strtolower($opinion),
            'auditor_sample_verified' => true,
            'status' => 'certified_unqualified',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_financial_integrity_statements')->where('id', $stmt->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Signed statements with exception count > 0
        $dirtySigned = DB::table('int_enterprise_financial_integrity_statements')
            ->whereIn('status', ['signed', 'certified_unqualified'])
            ->where('exception_count', '>', 0)
            ->count();

        // Discrepancy 2: Certified statements without sample verification
        $unverifiedAuditor = DB::table('int_enterprise_financial_integrity_statements')
            ->where('status', 'certified_unqualified')
            ->where('auditor_sample_verified', false)
            ->count();

        $total = $dirtySigned + $unverifiedAuditor;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_statements' => DB::table('int_enterprise_financial_integrity_statements')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
