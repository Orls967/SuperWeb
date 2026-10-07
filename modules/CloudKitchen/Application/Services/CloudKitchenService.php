<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\Core\Contracts\SimClockInterface;

class CloudKitchenService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function createSubscription(
        int $userId,
        string $packageName,
        int $dailyQuota,
        int $monthlyFeeIdr,
        int $dailyMealPriceIdr,
        Carbon $startDate,
        int $durationMonths = 1
    ): CateringSubscription {
        return DB::transaction(function () use ($userId, $packageName, $dailyQuota, $monthlyFeeIdr, $dailyMealPriceIdr, $startDate, $durationMonths) {
            $endDate = $startDate->copy()->addMonths($durationMonths);

            $sub = CateringSubscription::create([
                'subscription_code' => 'SUB-'.strtoupper(Str::random(8)),
                'user_id' => $userId,
                'package_name' => $packageName,
                'daily_quota' => $dailyQuota,
                'used_quota_today' => 0,
                'monthly_fee_idr' => $monthlyFeeIdr,
                'daily_meal_price_idr' => $dailyMealPriceIdr,
                'starts_at' => $startDate,
                'expires_at' => $endDate,
                'status' => 'active',
            ]);

            // Payroll deduction: Debit HCM Meals Deduction, Credit Resto Catering Revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'hcm_meal_payroll_deduction',
                description: "Monthly Catering Payroll Deduction for User {$userId}",
                idempotencyKey: "ck:sub:{$sub->id}",
                entries: [
                    PostingEntryDTO::forCode('hcm:meals_deduction:IDR', 'IDR', -$monthlyFeeIdr),
                    PostingEntryDTO::forCode('resto:catering_revenue:IDR', 'IDR', $monthlyFeeIdr),
                ],
                referenceType: 'catering_subscription',
                referenceId: (string) $sub->id,
            ));

            return $sub;
        });
    }

    public function dispatchMealOrder(
        CateringSubscription $subscription,
        string $mealSlot,
        Carbon $deliveryDate
    ): CateringOrder {
        return DB::transaction(function () use ($subscription, $mealSlot, $deliveryDate) {
            $dateStr = $deliveryDate->format('Y-m-d');

            // Guardrail: check daily quota
            $ordersToday = CateringOrder::where('subscription_id', $subscription->id)
                ->whereDate('delivery_date', $dateStr)
                ->count();

            if ($ordersToday >= $subscription->daily_quota) {
                throw new \InvalidArgumentException('Daily catering meal quota exceeded for this subscription.');
            }

            // Find kitchen with available hourly capacity
            $kitchen = CloudKitchen::where('is_active', true)
                ->whereColumn('current_hourly_load', '<', 'hourly_capacity')
                ->first();

            if (! $kitchen) {
                throw new \RuntimeException('No cloud kitchen currently has capacity available.');
            }

            $kitchen->current_hourly_load += 1;
            $kitchen->save();

            $idempotencyKey = "ck:order:{$subscription->id}:{$dateStr}:{$mealSlot}";

            return CateringOrder::create([
                'order_code' => 'ORD-'.strtoupper(Str::random(8)),
                'subscription_id' => $subscription->id,
                'kitchen_id' => $kitchen->id,
                'delivery_date' => $dateStr,
                'meal_slot' => $mealSlot,
                'status' => 'dispatched',
                'meal_cost_idr' => $subscription->daily_meal_price_idr,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    public function refundCancelledMeal(CateringOrder $order): void
    {
        DB::transaction(function () use ($order) {
            if ($order->status === 'refunded_closed') {
                return;
            }

            $order->status = 'refunded_closed';
            $order->save();

            // Refund ledger: Credit HCM Payroll deduction / User wallet, Debit Resto Catering Revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'catering_refund',
                description: "Refund for cancelled catering order {$order->order_code}",
                idempotencyKey: "ck:refund:{$order->id}",
                entries: [
                    PostingEntryDTO::forCode('resto:catering_revenue:IDR', 'IDR', -$order->meal_cost_idr),
                    PostingEntryDTO::forCode('hcm:meals_deduction:IDR', 'IDR', $order->meal_cost_idr),
                ],
                referenceType: 'catering_order',
                referenceId: (string) $order->id,
            ));
        });
    }
}
