<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Modules\EnterpriseFinance\Domain\Exceptions\SodViolationException;
use Modules\EnterpriseFinance\Domain\Models\SodRule;

/**
 * Mesin Evaluasi Matriks Segregation of Duties (SoD) (54.4 - COSO / ISO 27001).
 * Memeriksa pemisahan kewenangan pengguna pada tingkat aksi transaksi (Action-Level Guard):
 * - Pembuat Usulan (Maker) vs Penyetuju (Checker).
 * - Otorisator Pengadaan (Procurement) vs Eksekutor Pembayaran Kas (Payment Disburser).
 * - Administrator IT Sistem (IT Admin) vs Operator Keuangan (Finance Operator).
 * - Auditor Internal (Internal Auditor) vs Pemegang Kas (Cashier).
 */
class SodMatrixEvaluator
{
    /**
     * Mengevaluasi apakah daftar peran seorang pengguna memiliki konflik SoD aktif.
     *
     * @param  string[]  $userRoles
     * @return array{
     *     has_conflict: bool,
     *     is_blocked: bool,
     *     critical_conflicts_count: int,
     *     high_conflicts_count: int,
     *     conflicts: list<array{rule_code: string, role_a: string, role_b: string, risk_level: string, description: string}>
     * }
     */
    public function evaluateRoles(array $userRoles): array
    {
        $activeRules = SodRule::where('is_active', true)->get();
        $conflicts = [];
        $criticalCount = 0;
        $highCount = 0;

        foreach ($activeRules as $rule) {
            $hasA = in_array($rule->role_a, $userRoles, true);
            $hasB = in_array($rule->role_b, $userRoles, true);

            if ($hasA && $hasB) {
                if ($rule->risk_level === 'CRITICAL') {
                    $criticalCount++;
                } else {
                    $highCount++;
                }

                $conflicts[] = [
                    'rule_code' => $rule->rule_code,
                    'role_a' => $rule->role_a,
                    'role_b' => $rule->role_b,
                    'risk_level' => $rule->risk_level,
                    'description' => $rule->description,
                ];
            }
        }

        $hasConflict = (count($conflicts) > 0);
        $isBlocked = ($criticalCount > 0);

        return [
            'has_conflict' => $hasConflict,
            'is_blocked' => $isBlocked,
            'critical_conflicts_count' => $criticalCount,
            'high_conflicts_count' => $highCount,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Menegakkan guardrail SoD secara ketat: lempar SodViolationException jika ada konflik CRITICAL.
     *
     * @param  string[]  $userRoles
     *
     * @throws SodViolationException
     */
    public function enforceStrictSod(array $userRoles, string $actionContext): void
    {
        $evaluation = $this->evaluateRoles($userRoles);

        if ($evaluation['is_blocked']) {
            $first = $evaluation['conflicts'][0];
            throw new SodViolationException(
                "Pelanggaran Segregation of Duties pada konteks '{$actionContext}': Pengguna memiliki kombinasi peran terlarang [{$first['role_a']} & {$first['role_b']}] berisiko CRITICAL ({$first['rule_code']})."
            );
        }
    }
}
