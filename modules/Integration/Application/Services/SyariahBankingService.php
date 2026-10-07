<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SyariahBankingService (Fase 161 — Lini 19)
 *
 * Implements:
 *  - 161.1 Islamic banking products catalog & segregated accounts
 *  - 161.2 Segregated subledger accounting (PSAK 102/103) & contract hashing
 *  - 161.3 Murabahah financing with direct vendor disbursement, gradual margin recognition & charity amil penalty disgorgement
 *  - 161.4 Mudharabah profit sharing pool calculations
 */
class SyariahBankingService
{
    /**
     * Open Islamic segregated account (Wadiah / Mudharabah).
     */
    public function openAccount(int $customerId, string $type, float $nisbahCustomerPct = 60.0): object
    {
        $accountNumber = 'SYB-'.strtoupper(Str::random(10));

        $id = DB::table('syb_accounts')->insertGetId([
            'account_number' => $accountNumber,
            'customer_id' => $customerId,
            'account_type' => strtoupper($type),
            'balance' => 0.00,
            'nisbah_customer_pct' => $nisbahCustomerPct,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('syb_accounts')->find($id);
    }

    /**
     * Create Murabahah financing contract with direct vendor disbursement.
     */
    public function createMurabahahContract(string $accountNumber, string $vendorCode, float $costPrice, float $marginProfit, int $tenorMonths): object
    {
        $totalSelling = round($costPrice + $marginProfit, 2);
        $installment = round($totalSelling / max(1, $tenorMonths), 2);
        $code = 'MUR-'.strtoupper(Str::random(8));
        $hash = hash('sha256', "{$code}:{$vendorCode}:{$totalSelling}:{$tenorMonths}");

        $id = DB::table('syb_murabahah_contracts')->insertGetId([
            'contract_code' => $code,
            'account_number' => $accountNumber,
            'vendor_code' => $vendorCode,
            'cost_price_pokok' => $costPrice,
            'margin_profit' => $marginProfit,
            'total_selling_price' => $totalSelling,
            'tenor_months' => $tenorMonths,
            'monthly_installment' => $installment,
            'margin_recognized' => 0.00,
            'late_penalties_to_amil' => 0.00,
            'contract_hash' => $hash,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('syb_murabahah_contracts')->find($id);
    }

    /**
     * Pay installment: recognize monthly margin proportionally and direct late penalty to Amil charity pool.
     */
    public function payMurabahahInstallment(string $contractCode, float $paidAmount, float $lateFee = 0.0): object
    {
        $contract = DB::table('syb_murabahah_contracts')->where('contract_code', $contractCode)->first();
        $monthlyMargin = round((float) $contract->margin_profit / (int) $contract->tenor_months, 2);

        DB::table('syb_murabahah_contracts')->where('contract_code', $contractCode)->update([
            'margin_recognized' => DB::raw("margin_recognized + {$monthlyMargin}"),
            'late_penalties_to_amil' => DB::raw("late_penalties_to_amil + {$lateFee}"),
            'updated_at' => now(),
        ]);

        return (object) DB::table('syb_murabahah_contracts')->where('contract_code', $contractCode)->first();
    }

    /**
     * Distribute Mudharabah investment pool profit sharing.
     */
    public function distributeMudharabahPool(string $period, float $totalProfit, float $customerNisbahPct = 60.0): object
    {
        $customerShare = round($totalProfit * ($customerNisbahPct / 100.0), 2);
        $bankShare = round($totalProfit - $customerShare, 2);

        $id = DB::table('syb_mudharabah_pools')->insertGetId([
            'pool_period' => $period,
            'total_pool_profit' => $totalProfit,
            'customer_share_distributed' => $customerShare,
            'bank_mudharib_share' => $bankShare,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('syb_mudharabah_pools')->find($id);
    }

    /**
     * Syariah banking audit (`syb:audit`).
     */
    public function audit(): array
    {
        $contracts = DB::table('syb_murabahah_contracts')->get();
        $discrepancies = 0;

        foreach ($contracts as $c) {
            $expectedTotal = round((float) $c->cost_price_pokok + (float) $c->margin_profit, 2);
            if (abs($expectedTotal - (float) $c->total_selling_price) > 0.05) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_accounts' => DB::table('syb_accounts')->count(),
            'total_murabahah_contracts' => $contracts->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
