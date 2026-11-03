<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Ret\Domain\Exceptions\CouponAlreadyRedeemedException;
use Modules\Ret\Domain\Models\RetCouponRedemption;
use Modules\Shared\Application\BaseAction;

class ApplyCrossChannelCouponAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetCouponRedemption
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required to apply cross-channel coupon.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetCouponRedemption::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $couponCode = strtoupper(trim((string) $params['coupon_code']));
        $customerId = (string) $params['customer_id'];
        $channelId = (string) ($params['channel_id'] ?? 'web');
        $orderAmountMinor = (int) ($params['order_amount_minor'] ?? 0);

        // Anti-double-use check
        $alreadyUsed = RetCouponRedemption::where('coupon_code', $couponCode)
            ->where('customer_id', $customerId)
            ->exists();

        if ($alreadyUsed) {
            throw new CouponAlreadyRedeemedException($couponCode, $customerId);
        }

        return DB::transaction(function () use ($couponCode, $customerId, $channelId, $orderAmountMinor, $idempotencyKey) {
            // 10% coupon promo calculation
            $discountMinor = (int) round(($orderAmountMinor * 10) / 100);

            $redemption = RetCouponRedemption::create([
                'id' => (string) Str::uuid(),
                'coupon_code' => $couponCode,
                'customer_id' => $customerId,
                'order_id' => 'ORD-'.strtoupper(Str::random(8)),
                'channel_id' => $channelId,
                'discount_minor' => $discountMinor,
                'redeemed_at' => Carbon::now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'ret_coupon_redeemed',
                auditable: $redemption,
                newValues: ['coupon' => $couponCode, 'discount' => $discountMinor],
                impactType: 'customer_promotions',
            );

            return $redemption;
        });
    }
}
