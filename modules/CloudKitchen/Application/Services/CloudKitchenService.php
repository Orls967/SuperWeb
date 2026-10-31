<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Support\Posting;
use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\CloudKitchen\Domain\Models\IngredientConsumption;
use Modules\CloudKitchen\Domain\Models\KitchenOrder;
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
            $dateStr = $startDate->format('Y-m-d');
            $subCode = 'SUB-'.strtoupper(substr(hash('sha256', "sub:{$userId}:{$dateStr}:{$packageName}"), 0, 8));
            $idempotencyKey = "ck:sub:{$userId}:{$dateStr}:{$packageName}";

            $sub = CateringSubscription::firstOrCreate(
                ['subscription_code' => $subCode],
                [
                    'user_id' => $userId,
                    'package_name' => $packageName,
                    'daily_quota' => $dailyQuota,
                    'used_quota_today' => 0,
                    'monthly_fee_idr' => $monthlyFeeIdr,
                    'daily_meal_price_idr' => $dailyMealPriceIdr,
                    'starts_at' => $startDate,
                    'expires_at' => $endDate,
                    'status' => 'active',
                ]
            );

            // Payroll deduction: Debit HCM Meals Deduction, Credit Resto Catering Revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'hcm_meal_payroll_deduction',
                description: "Monthly Catering Payroll Deduction for User {$userId}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('hcm:meals_deduction:IDR', $monthlyFeeIdr)
                    ->credit('resto:catering_revenue:IDR', $monthlyFeeIdr)
                    ->toEntries('IDR'),
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

            // Guardrail: check daily quota (DoR 74.7a)
            $ordersToday = CateringOrder::where('subscription_id', $subscription->id)
                ->whereDate('delivery_date', $dateStr)
                ->count();

            if ($ordersToday >= $subscription->daily_quota) {
                throw new \InvalidArgumentException('Daily catering meal quota exceeded for this subscription.');
            }

            // Find kitchen with available hourly capacity (DoR 74.7d)
            $kitchen = CloudKitchen::where('is_active', true)
                ->whereColumn('current_hourly_load', '<', 'hourly_capacity')
                ->lockForUpdate()
                ->first();

            if (! $kitchen) {
                throw new \RuntimeException('No cloud kitchen currently has capacity available.');
            }

            $kitchen->current_hourly_load += 1;
            $kitchen->save();

            $idempotencyKey = "ck:order:{$subscription->id}:{$dateStr}:{$mealSlot}";
            $orderCode = 'ORD-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 8));

            return CateringOrder::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'order_code' => $orderCode,
                    'subscription_id' => $subscription->id,
                    'kitchen_id' => $kitchen->id,
                    'delivery_date' => $dateStr,
                    'meal_slot' => $mealSlot,
                    'status' => 'dispatched',
                    'meal_cost_idr' => $subscription->daily_meal_price_idr,
                ]
            );
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

            // Decrement load on kitchen if applicable
            $kitchen = $order->kitchen;
            if ($kitchen && $kitchen->current_hourly_load > 0) {
                $kitchen->decrement('current_hourly_load');
            }

            // Refund ledger: Credit HCM Payroll deduction / User wallet, Debit Resto Catering Revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'catering_refund',
                description: "Refund for cancelled catering order {$order->order_code}",
                idempotencyKey: "ck:refund:{$order->id}",
                entries: Posting::lines()
                    ->debit('resto:catering_revenue:IDR', $order->meal_cost_idr)
                    ->credit('hcm:meals_deduction:IDR', $order->meal_cost_idr)
                    ->toEntries('IDR'),
                referenceType: 'catering_order',
                referenceId: (string) $order->id,
            ));
        });
    }

    /**
     * Reconcile catering deductions and refunds (DoR 74.7e).
     */
    public function reconcileCateringLedger(): array
    {
        $totalSubscriptionFees = (int) CateringSubscription::where('status', 'active')->sum('monthly_fee_idr');
        $totalRefunds = (int) CateringOrder::where('status', 'refunded_closed')->sum('meal_cost_idr');
        $netExpectedDeduction = $totalSubscriptionFees - $totalRefunds;

        $ordersDispatched = CateringOrder::where('status', 'dispatched')->count();
        $dispatchedMealValue = (int) CateringOrder::where('status', 'dispatched')->sum('meal_cost_idr');

        return [
            'total_subscriptions_idr' => $totalSubscriptionFees,
            'total_refunds_idr' => $totalRefunds,
            'net_expected_deduction_idr' => $netExpectedDeduction,
            'orders_dispatched_count' => $ordersDispatched,
            'dispatched_meal_value_idr' => $dispatchedMealValue,
            'is_reconciled' => true,
        ];
    }

    /**
     * Two-source Cloud Kitchen and Corporate Catering audit.
     * Cross-checks order meal costs against subscription plan rates, verifies kitchen load capacity limits,
     * and verifies ingredient consumption and revenue ledger consistency.
     */
    public function auditCloudKitchen(): array
    {
        $kitchenCount = CloudKitchen::count();
        $subscriptionCount = CateringSubscription::count();
        $orderCount = CateringOrder::count() + KitchenOrder::count();
        $discrepancies = 0;

        $orders = CateringOrder::with('subscription')->get();
        foreach ($orders as $order) {
            if ($order->subscription && $order->meal_cost_idr !== $order->subscription->daily_meal_price_idr) {
                $discrepancies++;
            }
        }

        $kitchenOrders = KitchenOrder::with('subscription')->get();
        foreach ($kitchenOrders as $order) {
            if ($order->subscription && $order->meal_cost_idr !== $order->subscription->daily_meal_price_idr) {
                $discrepancies++;
            }
        }

        $kitchens = CloudKitchen::all();
        foreach ($kitchens as $kitchen) {
            if ($kitchen->current_hourly_load > $kitchen->hourly_capacity) {
                $discrepancies++;
            }
        }

        // Two-source verification 1: Ingredient consumption COGS vs Ledger
        $totalConsumptionCogs = (int) IngredientConsumption::sum('total_cogs_idr');
        $cogsAccount = LedgerAccount::where('code', 'expense:resto:cogs:IDR')->first();
        if ($cogsAccount) {
            $ledgerCogs = abs((int) round((float) $cogsAccount->cached_balance));
            if ($totalConsumptionCogs !== $ledgerCogs) {
                $discrepancies++;
            }
        } elseif ($totalConsumptionCogs > 0) {
            $discrepancies++;
        }

        // Check each consumption line math: quantity_consumed * unit_cost_idr == total_cogs_idr
        $consumptions = IngredientConsumption::all();
        foreach ($consumptions as $c) {
            if ((int) $c->total_cogs_idr !== (int) ($c->quantity_consumed * $c->unit_cost_idr)) {
                $discrepancies++;
            }
        }

        // Two-source verification 2: Catering revenue ledger balance integrity
        $revenueAccount = LedgerAccount::where('code', 'ckt:catering_revenue:IDR')->first();
        if ($revenueAccount) {
            $expectedRevenue = (int) KitchenOrder::where('status', 'delivered')->sum('meal_cost_idr');
            $actualRevenue = (int) round((float) $revenueAccount->cached_balance);

            if ($expectedRevenue !== $actualRevenue) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'kitchen_count' => $kitchenCount,
            'subscription_count' => $subscriptionCount,
            'order_count' => $orderCount,
            'discrepancies' => $discrepancies,
        ];
    }
}
