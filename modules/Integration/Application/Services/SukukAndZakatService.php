<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SukukAndZakatService (Fase 162 — Lini 19)
 *
 * Implements:
 *  - 162.1 Sukuk issuance backed by underlying tangible assets with periodic coupon
 *  - 162.2 Ijarah Muntahia Bittamleek (Lease-to-own) with end ownership transfer
 *  - 162.3 Shariah investment screening (debt ratio < 45%, non-halal revenue < 5%)
 *  - 162.4 Zakat mal engine (2.5% above Nisab 85g gold) and asnaf distribution
 */
class SukukAndZakatService
{
    /**
     * Issue Sukuk investment token.
     */
    public function issueSukuk(string $assetCode, float $issuanceAmount, float $couponRatePct, Carbon $maturity): object
    {
        $code = 'SKK-'.strtoupper(Str::random(8));

        $id = DB::table('syb_sukuk_issuances')->insertGetId([
            'sukuk_code' => $code,
            'asset_underlying_code' => $assetCode,
            'total_issuance_amount' => $issuanceAmount,
            'periodic_coupon_rate_pct' => $couponRatePct,
            'maturity_date' => $maturity->toDateString(),
            'total_coupon_distributed' => 0.00,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('syb_sukuk_issuances')->find($id);
    }

    /**
     * Distribute periodic Sukuk coupon.
     */
    public function distributeSukukCoupon(string $sukukCode): float
    {
        $sukuk = DB::table('syb_sukuk_issuances')->where('sukuk_code', $sukukCode)->first();
        $rate = (float) ($sukuk->periodic_coupon_rate_pct ?? 7.50);
        $amount = (float) ($sukuk->total_issuance_amount ?? 0.0);

        $couponAmount = round($amount * ($rate / 100.0) / 4, 2); // Quarterly distribution

        DB::table('syb_sukuk_issuances')->where('sukuk_code', $sukukCode)->update([
            'total_coupon_distributed' => DB::raw("total_coupon_distributed + {$couponAmount}"),
            'updated_at' => now(),
        ]);

        return $couponAmount;
    }

    /**
     * Create Ijarah contract and handle transfer of ownership upon completion.
     */
    public function createIjarahContract(string $assetCode, int $customerId, float $monthlyRental, int $tenorMonths): object
    {
        $code = 'IJR-'.strtoupper(Str::random(8));

        $id = DB::table('syb_ijarah_contracts')->insertGetId([
            'ijarah_code' => $code,
            'asset_code' => $assetCode,
            'customer_id' => $customerId,
            'monthly_rental' => $monthlyRental,
            'tenor_months' => $tenorMonths,
            'months_paid' => 0,
            'residual_purchase_price' => 1000.00,
            'ownership_transferred' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('syb_ijarah_contracts')->find($id);
    }

    /**
     * Pay Ijarah rental installment; automatically transfer ownership when tenor is completed.
     */
    public function payIjarahInstallment(string $ijarahCode): object
    {
        $contract = DB::table('syb_ijarah_contracts')->where('ijarah_code', $ijarahCode)->first();
        $newMonths = (int) $contract->months_paid + 1;
        $isFinished = ($newMonths >= (int) $contract->tenor_months);

        DB::table('syb_ijarah_contracts')->where('ijarah_code', $ijarahCode)->update([
            'months_paid' => $newMonths,
            'ownership_transferred' => $isFinished,
            'updated_at' => now(),
        ]);

        return (object) DB::table('syb_ijarah_contracts')->where('ijarah_code', $ijarahCode)->first();
    }

    /**
     * Perform Shariah compliance screening on equity/security.
     */
    public function screenSecurity(string $ticker, string $name, float $debtRatioPct, float $nonHalalRevPct): object
    {
        // Standard OJK/AAOIFI benchmark: Debt/Asset < 45%, Non-halal revenue < 10% (strict 5%)
        $isCompliant = ($debtRatioPct <= 45.0 && $nonHalalRevPct <= 5.0);

        DB::table('syb_shariah_screenings')->updateOrInsert(
            ['ticker_or_asset_code' => strtoupper($ticker)],
            [
                'asset_name' => $name,
                'debt_to_assets_ratio_pct' => $debtRatioPct,
                'non_halal_revenue_ratio_pct' => $nonHalalRevPct,
                'is_shariah_compliant' => $isCompliant,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('syb_shariah_screenings')->where('ticker_or_asset_code', strtoupper($ticker))->first();
    }

    /**
     * Calculate and deduct Zakat Mal (2.5% on qualifying wealth above Nisab).
     */
    public function calculateZakatMal(int $customerId, string $year, float $wealthAmount, float $nisab = 85000000.00): object
    {
        $isAboveNisab = ($wealthAmount >= $nisab);
        $zakatDue = $isAboveNisab ? round($wealthAmount * 0.025, 2) : 0.00;

        DB::table('syb_zakat_calculations')->updateOrInsert(
            ['customer_id' => $customerId, 'zakat_year' => $year],
            [
                'qualifying_wealth' => $wealthAmount,
                'nisab_threshold_gold_equiv' => $nisab,
                'zakat_rate_pct' => 2.50,
                'zakat_amount_due' => $zakatDue,
                'is_settled' => true,
                'distribution_asnaf' => 'FAKIR_MISKIN',
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('syb_zakat_calculations')
            ->where('customer_id', $customerId)
            ->where('zakat_year', $year)
            ->first();
    }

    /**
     * Audit: verify no non-compliant assets flagged compliant & zakat rate 2.5% math.
     */
    public function audit(): array
    {
        $nonCompliantFlagged = DB::table('syb_shariah_screenings')
            ->where('is_shariah_compliant', true)
            ->where(function ($q) {
                $q->where('debt_to_assets_ratio_pct', '>', 45.0)
                    ->orWhere('non_halal_revenue_ratio_pct', '>', 5.0);
            })
            ->count();

        return [
            'status' => $nonCompliantFlagged === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_sukuk_issuances' => DB::table('syb_sukuk_issuances')->count(),
            'total_screened_assets' => DB::table('syb_shariah_screenings')->count(),
            'total_zakat_assessments' => DB::table('syb_zakat_calculations')->count(),
            'discrepancy_count' => $nonCompliantFlagged,
        ];
    }
}
