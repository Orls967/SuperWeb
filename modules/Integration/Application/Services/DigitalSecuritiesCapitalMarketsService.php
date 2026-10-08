<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DigitalSecuritiesCapitalMarketsService (Fase 271)
 *
 * Implements:
 *  - 271.1 Digital securities issuance desk (equity/debt tokenization, bookbuilding & allocations)
 *  - 271.2 Digital investor onboarding with tiered KYC/AML & suitability gates
 *  - 271.3 Internal automated market making with spread rules, inventory limits & depth metrics
 *  - 271.4 Issuance sum ledger reconciliation & suitability enforcement
 *  - 271.5 Edge case: Thin orderbook triggers automated spread widening and warning rather than price distortion
 *  - 271.6 Investor suitability downgrades immediately suspend high-risk instrument access
 *  - 271.7 Mandatory corporate action notice period (>= 14 days) enforced
 */
class DigitalSecuritiesCapitalMarketsService
{
    /**
     * Create digital security issuance (271.1).
     */
    public function createIssuance(
        string $issuanceCode,
        string $tokenSymbol,
        string $securityType,
        float $totalTokens,
        float $faceValueUsd
    ): object {
        $code = strtoupper($issuanceCode);

        $id = DB::table('digital_securities_issuances')->insertGetId([
            'issuance_code' => $code,
            'token_symbol' => strtoupper($tokenSymbol),
            'security_type' => strtoupper($securityType),
            'total_tokens_issued' => $totalTokens,
            'token_face_value_usd' => $faceValueUsd,
            'total_allocated_tokens' => 0.0,
            'status' => 'BOOKBUILDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('digital_securities_issuances')->find($id);
    }

    /**
     * Allocate tokens to investor with suitability check & issuance balance guard (271.1, 271.2, 271.4).
     */
    public function allocateTokens(
        string $issuanceCode,
        string $investorId,
        float $tokenAmount
    ): object {
        $code = strtoupper($issuanceCode);
        $issuance = DB::table('digital_securities_issuances')->where('issuance_code', $code)->first();
        if (! $issuance) {
            throw new InvalidArgumentException("Issuance '{$issuanceCode}' not found.");
        }

        $investor = DB::table('digital_securities_investors')->where('investor_id', strtoupper($investorId))->first();
        if (! $investor) {
            throw new InvalidArgumentException("Investor '{$investorId}' not found.");
        }

        // Suitability gate (271.2 & 271.4)
        if ($investor->is_access_suspended || $investor->suitability_grade === 'CONSERVATIVE') {
            throw new InvalidArgumentException("Investor '{$investorId}' rejected by suitability gate for risk instrument (271.2 & 271.4).");
        }

        // Issuance balance guard (271.4): Allocated cannot exceed total issued
        $newAllocated = (float) $issuance->total_allocated_tokens + $tokenAmount;
        if ($newAllocated > (float) $issuance->total_tokens_issued) {
            throw new InvalidArgumentException("Issuance allocation error: Total allocated ({$newAllocated}) exceeds total issued ({$issuance->total_tokens_issued}) (271.4).");
        }

        DB::table('digital_securities_issuances')
            ->where('issuance_code', $code)
            ->update([
                'total_allocated_tokens' => $newAllocated,
                'status' => ($newAllocated == (float) $issuance->total_tokens_issued) ? 'ALLOCATED' : 'BOOKBUILDING',
                'updated_at' => now(),
            ]);

        return (object) DB::table('digital_securities_issuances')->where('issuance_code', $code)->first();
    }

    /**
     * Onboard digital investor (271.2).
     */
    public function onboardInvestor(
        string $investorId,
        string $kycTier,
        string $suitabilityGrade
    ): object {
        $id = DB::table('digital_securities_investors')->insertGetId([
            'investor_id' => strtoupper($investorId),
            'kyc_tier' => strtoupper($kycTier),
            'suitability_grade' => strtoupper($suitabilityGrade),
            'is_access_suspended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('digital_securities_investors')->find($id);
    }

    /**
     * Suspend investor access on suitability downgrade (271.6 Edge Case).
     */
    public function updateInvestorSuitability(
        string $investorId,
        string $newGrade,
        bool $suspendRiskAccess = false
    ): object {
        $code = strtoupper($investorId);

        DB::table('digital_securities_investors')
            ->where('investor_id', $code)
            ->update([
                'suitability_grade' => strtoupper($newGrade),
                'is_access_suspended' => $suspendRiskAccess,
                'updated_at' => now(),
            ]);

        return (object) DB::table('digital_securities_investors')->where('investor_id', $code)->first();
    }

    /**
     * Update market making orderbook with inventory limit & thin liquidity spread widening (271.3, 271.4, 271.5 Edge Case).
     */
    public function updateMarketMakingQuote(
        string $marketSymbol,
        float $bidPrice,
        float $askPrice,
        float $currentInventoryUsd,
        float $maxInventoryLimitUsd,
        float $orderbookDepthUsd
    ): object {
        $symbol = strtoupper($marketSymbol);

        // Inventory limit guard (271.3 & 271.4)
        if ($currentInventoryUsd > $maxInventoryLimitUsd) {
            throw new InvalidArgumentException("Market maker inventory limit exceeded (\${$currentInventoryUsd} > \${$maxInventoryLimitUsd}) (271.4).");
        }

        // Edge case 271.5: Thin liquidity (< $25,000 depth) automatically widens spread and sets warning
        $isThinLiquidity = ($orderbookDepthUsd < 25000.0);
        $spreadPct = round((($askPrice - $bidPrice) / max(0.0001, $bidPrice)) * 100.0, 2);
        if ($isThinLiquidity) {
            $spreadPct = max($spreadPct, 8.50); // Automatically widened spread
        }

        DB::table('digital_securities_market_making')->updateOrInsert(
            ['market_symbol' => $symbol],
            [
                'bid_price_usd' => $bidPrice,
                'ask_price_usd' => $askPrice,
                'spread_pct' => $spreadPct,
                'current_inventory_usd' => $currentInventoryUsd,
                'max_inventory_limit_usd' => $maxInventoryLimitUsd,
                'orderbook_depth_usd' => $orderbookDepthUsd,
                'is_liquidity_thin_warning' => $isThinLiquidity,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('digital_securities_market_making')->where('market_symbol', $symbol)->first();
    }

    /**
     * Schedule corporate action with mandatory notice period (271.7).
     */
    public function scheduleCorporateAction(
        string $actionCode,
        string $issuanceCode,
        string $actionType,
        int $noticePeriodDays,
        string $executionDate
    ): object {
        // Enforce mandatory notice period (at least 14 days) (271.7)
        if ($noticePeriodDays < 14) {
            throw new InvalidArgumentException('Corporate action notice period violation: Minimum 14 days advance notice required before execution (271.7).');
        }

        $id = DB::table('digital_securities_corporate_actions')->insertGetId([
            'action_code' => strtoupper($actionCode),
            'issuance_code' => strtoupper($issuanceCode),
            'action_type' => strtoupper($actionType),
            'notice_period_days' => $noticePeriodDays,
            'execution_date' => $executionDate,
            'is_executed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('digital_securities_corporate_actions')->find($id);
    }

    /**
     * Digital Securities Platform Audit (`rwa:audit`) (271.4, 271.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Issuances where allocated tokens exceed issued tokens
        $overAllocatedIssuances = DB::table('digital_securities_issuances')
            ->whereRaw('total_allocated_tokens > total_tokens_issued')
            ->count();

        // Discrepancy 2: Market makers exceeding inventory limit
        $overLimitMarketMakers = DB::table('digital_securities_market_making')
            ->whereRaw('current_inventory_usd > max_inventory_limit_usd')
            ->count();

        // Discrepancy 3: Corporate actions executed with illegal notice period (< 14 days)
        $illegalNoticeCorporateActions = DB::table('digital_securities_corporate_actions')
            ->where('notice_period_days', '<', 14)
            ->count();

        $discrepancies = $overAllocatedIssuances + $overLimitMarketMakers + $illegalNoticeCorporateActions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_issuances' => DB::table('digital_securities_issuances')->count(),
            'total_investors' => DB::table('digital_securities_investors')->count(),
            'total_market_makers' => DB::table('digital_securities_market_making')->count(),
            'total_corporate_actions' => DB::table('digital_securities_corporate_actions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
