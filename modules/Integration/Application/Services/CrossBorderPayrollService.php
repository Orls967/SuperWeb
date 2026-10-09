<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CrossBorderPayrollService (Fase 152)
 *
 * Implements:
 *  - 152.1 Global payroll engine for simulated multi-country rules
 *  - 152.2 Expatriate assignment contracts and validation
 *  - 152.3 Immigration compliance and work permit block
 *  - 152.4 Tax equalization and shadow payroll tracking
 *  - 152.5 Consolidation to parent company cost
 */
class CrossBorderPayrollService
{
    /**
     * Seed or update payroll rules for countries.
     */
    public function setPayrollRule(string $countryCode, string $currency, float $taxRate, float $socialRate, bool $has13th = false): void
    {
        DB::table('gbl_country_payroll_rules')->updateOrInsert(
            ['country_code' => strtoupper($countryCode)],
            [
                'currency' => strtoupper($currency),
                'income_tax_rate_pct' => $taxRate,
                'social_security_rate_pct' => $socialRate,
                'has_13th_month' => $has13th,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Register an expatriate assignment, checking valid work permit.
     */
    public function assignExpatriate(int $userId, string $homeCountry, string $hostCountry, Carbon $startDate, Carbon $endDate, float $baseSalary, string $currency): object
    {
        $host = strtoupper($hostCountry);

        // Check valid permit
        $permit = DB::table('gbl_work_permits')
            ->where('user_id', $userId)
            ->where('country_code', $host)
            ->where('valid_until', '>=', $endDate->toDateString())
            ->where('status', 'ACTIVE')
            ->first();

        if (! $permit) {
            throw new \RuntimeException("Cannot assign expatriate to {$host}: No valid work permit found for assignment duration.");
        }

        $code = 'EXP-'.strtoupper(Str::random(8));

        $id = DB::table('gbl_expatriate_assignments')->insertGetId([
            'assignment_code' => $code,
            'user_id' => $userId,
            'home_country' => strtoupper($homeCountry),
            'host_country' => $host,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'base_salary' => $baseSalary,
            'currency' => strtoupper($currency),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gbl_expatriate_assignments')->find($id);
    }

    /**
     * Issue work permit.
     */
    public function issueWorkPermit(int $userId, string $countryCode, string $permitNumber, Carbon $validUntil): object
    {
        $id = DB::table('gbl_work_permits')->insertGetId([
            'user_id' => $userId,
            'country_code' => strtoupper($countryCode),
            'permit_number' => $permitNumber,
            'valid_until' => $validUntil->toDateString(),
            'status' => $validUntil->isPast() ? 'EXPIRED' : 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gbl_work_permits')->find($id);
    }

    /**
     * Run pay run for country/assignment with optional shadow payroll.
     */
    public function executePayRun(string $countryCode, float $grossPay, float $rateToIdr, ?string $assignmentCode = null, bool $isShadow = false): object
    {
        $country = strtoupper($countryCode);
        $rule = DB::table('gbl_country_payroll_rules')->where('country_code', $country)->first();

        $taxRate = (float) ($rule->income_tax_rate_pct ?? 20.0);
        $socialRate = (float) ($rule->social_security_rate_pct ?? 5.0);
        $currency = $rule->currency ?? 'USD';

        $taxAmount = round($grossPay * ($taxRate / 100.0), 2);
        $socialAmount = round($grossPay * ($socialRate / 100.0), 2);
        $netPay = round($grossPay - $taxAmount - $socialAmount, 2);

        $shadowTax = $isShadow ? round($grossPay * 0.15, 2) : 0.00; // Shadow payroll for dual-reporting
        $consolidatedCostIdr = round(($grossPay + $shadowTax) * $rateToIdr, 2);

        $runCode = 'PAYRUN-'.strtoupper(Str::random(8));

        $id = DB::table('gbl_pay_run_records')->insertGetId([
            'run_code' => $runCode,
            'assignment_code' => $assignmentCode,
            'country_code' => $country,
            'gross_pay' => $grossPay,
            'tax_amount' => $taxAmount,
            'social_security_amount' => $socialAmount,
            'net_pay' => $netPay,
            'shadow_payroll_tax' => $shadowTax,
            'is_shadow' => $isShadow,
            'currency' => $currency,
            'consolidated_cost_idr' => $consolidatedCostIdr,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gbl_pay_run_records')->find($id);
    }

    /**
     * Audit: verify no invalid expatriates without valid permit & consistency.
     */
    public function audit(): array
    {
        $discrepancies = 0;

        // Check active assignments without valid permit
        $assignments = DB::table('gbl_expatriate_assignments')->where('status', 'ACTIVE')->get();
        foreach ($assignments as $a) {
            $hasPermit = DB::table('gbl_work_permits')
                ->where('user_id', $a->user_id)
                ->where('country_code', $a->host_country)
                ->where('status', 'ACTIVE')
                ->where('valid_until', '>=', $a->end_date)
                ->exists();

            if (! $hasPermit) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'active_assignments' => $assignments->count(),
            'total_pay_runs' => DB::table('gbl_pay_run_records')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
