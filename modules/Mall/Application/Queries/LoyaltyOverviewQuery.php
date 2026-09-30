<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Mall\Domain\Enums\LoyaltyTier;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\ReceiptClaim;
use Modules\Mall\Domain\Models\Voucher;
use Modules\Mall\Domain\Models\VoucherTemplate;

class LoyaltyOverviewQuery
{
    /**
     * @return array{
     *     total_points_issued: int,
     *     total_points_active: int,
     *     total_points_expired: int,
     *     member_counts: array<string, int>,
     *     voucher_stats: array{
     *         total_templates: int,
     *         active_vouchers: int,
     *         used_vouchers: int,
     *         settled_amount: int
     *     },
     *     recent_claims: Collection,
     *     templates: Collection
     * }
     */
    public function get(): array
    {
        $totalIssued = (int) PointBatch::sum('points_earned');
        $totalActive = (int) PointBatch::where('is_expired', false)->sum('points_remaining');
        $totalExpired = (int) PointBatch::where('is_expired', true)->sum('points_earned') - (int) PointBatch::where('is_expired', true)->sum('points_remaining');

        $silverCount = LoyaltyMember::where('tier', LoyaltyTier::SILVER)->count();
        $goldCount = LoyaltyMember::where('tier', LoyaltyTier::GOLD)->count();
        $platinumCount = LoyaltyMember::where('tier', LoyaltyTier::PLATINUM)->count();

        $activeVouchers = Voucher::where('status', VoucherStatus::ACTIVE)->count();
        $usedVouchers = Voucher::whereIn('status', [VoucherStatus::USED, VoucherStatus::SETTLED])->count();
        $settledAmount = (int) Voucher::where('status', VoucherStatus::SETTLED)->sum('nominal_value');

        $templates = VoucherTemplate::where('is_active', true)->get();
        $recentClaims = ReceiptClaim::with(['user', 'tenant'])
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'total_points_issued' => $totalIssued,
            'total_points_active' => $totalActive,
            'total_points_expired' => max(0, $totalIssued - $totalActive),
            'member_counts' => [
                'silver' => $silverCount,
                'gold' => $goldCount,
                'platinum' => $platinumCount,
                'total' => $silverCount + $goldCount + $platinumCount,
            ],
            'voucher_stats' => [
                'total_templates' => $templates->count(),
                'active_vouchers' => $activeVouchers,
                'used_vouchers' => $usedVouchers,
                'settled_amount' => $settledAmount,
            ],
            'recent_claims' => $recentClaims,
            'templates' => $templates,
        ];
    }
}
