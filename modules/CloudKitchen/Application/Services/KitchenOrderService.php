<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\IngredientConsumption;
use Modules\CloudKitchen\Domain\Models\KitchenOrder;
use Modules\CloudKitchen\Domain\Models\KitchenRecipe;
use Modules\Shared\Domain\Math\HamiltonApportionment;

class KitchenOrderService
{
    public function __construct(
        protected KitchenStationService $stationService,
        protected RecipeBomService $bomService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Submit a meal order under a catering subscription.
     *
     * @throws InvalidArgumentException|DomainException
     */
    public function orderMeal(
        int $subscriptionId,
        string $mealSlot,
        string $deliveryDate,
        string $idempotencyKey,
        ?int $recipeId = null
    ): KitchenOrder {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $payloadHash = hash('sha256', json_encode([
            'subscription_id' => $subscriptionId,
            'meal_slot' => $mealSlot,
            'delivery_date' => $deliveryDate,
            'recipe_id' => $recipeId,
        ]));

        $existing = KitchenOrder::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->payload_hash !== null && $existing->payload_hash !== $payloadHash) {
                throw new DomainException("Idempotency conflict: key '{$idempotencyKey}' was used with different payload.");
            }

            return $existing;
        }

        $sub = CateringSubscription::findOrFail($subscriptionId);
        if ($sub->status !== 'active') {
            throw new DomainException('Catering subscription is not active');
        }

        // Daily quota check
        $ordersTodayCount = KitchenOrder::where('subscription_id', $sub->id)
            ->whereDate('delivery_date', $deliveryDate)
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($ordersTodayCount >= $sub->daily_quota) {
            throw new DomainException('Daily catering meal quota exceeded');
        }

        $kitchen = $this->stationService->findAvailableKitchenWithCapacity();

        return DB::transaction(function () use ($sub, $kitchen, $recipeId, $deliveryDate, $mealSlot, $idempotencyKey, $payloadHash): KitchenOrder {
            $orderCode = 'CK-ORD-'.date('Ymd').'-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 8));

            $order = KitchenOrder::create([
                'order_code' => $orderCode,
                'subscription_id' => $sub->id,
                'kitchen_id' => $kitchen->id,
                'recipe_id' => $recipeId,
                'delivery_date' => $deliveryDate,
                'meal_slot' => $mealSlot,
                'status' => 'pending',
                'meal_cost_idr' => $sub->daily_meal_price_idr,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
            ]);

            $kitchen->increment('current_hourly_load');

            return $order;
        });
    }

    /**
     * Start kitchen production, consuming raw materials via BOM explosion (B0′.4) and posting COGS.
     */
    public function startProduction(int $orderId): KitchenOrder
    {
        return DB::transaction(function () use ($orderId): KitchenOrder {
            $order = KitchenOrder::findOrFail($orderId);
            $recipeId = $order->recipe_id ?? KitchenRecipe::first()?->id;

            $totalCogs = 0;
            if ($recipeId) {
                $rawMaterials = $this->bomService->resolveRawMaterials($recipeId, 1);
                foreach ($rawMaterials as $code => $qty) {
                    $unitCost = 25; // standard base cost per gram
                    $cogs = $qty * $unitCost;
                    $totalCogs += $cogs;

                    IngredientConsumption::create([
                        'order_id' => $order->id,
                        'ingredient_code' => $code,
                        'quantity_consumed' => $qty,
                        'unit_cost_idr' => $unitCost,
                        'total_cogs_idr' => $cogs,
                        'movement_idempotency_key' => "ck:mov:{$order->id}:{$code}",
                    ]);
                }
            }

            if ($totalCogs > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'kitchen_production_cogs',
                    description: "COGS for Order {$order->order_code}",
                    idempotencyKey: "ck:cogs:{$order->id}",
                    entries: Posting::lines()
                        ->debit('expense:resto:cogs:IDR', $totalCogs)
                        ->credit('inventory:resto:central:IDR', $totalCogs)
                        ->toEntries('IDR'),
                    referenceType: 'catering_order',
                    referenceId: (string) $order->id
                ));
            }

            $order->status = 'preparing';
            $order->save();

            return $order;
        });
    }

    /**
     * Group ready orders into multi-drop courier delivery batch.
     *
     * @param  list<int>  $orderIds
     * @return array{batch_code: string, orders_count: int, courier_name: string}
     */
    public function createCourierDeliveryBatch(array $orderIds, string $courierName): array
    {
        return DB::transaction(function () use ($orderIds, $courierName): array {
            $batchCode = 'BATCH-'.date('YmdHis').'-'.count($orderIds);

            KitchenOrder::whereIn('id', $orderIds)->update([
                'status' => 'in_transit',
                'courier_batch_ref' => $batchCode,
            ]);

            return [
                'batch_code' => $batchCode,
                'orders_count' => count($orderIds),
                'courier_name' => $courierName,
            ];
        });
    }

    /**
     * Mark meal order as delivered and release kitchen load.
     */
    public function markDelivered(int $orderId): KitchenOrder
    {
        return DB::transaction(function () use ($orderId): KitchenOrder {
            $order = KitchenOrder::findOrFail($orderId);
            $order->status = 'delivered';
            $order->save();

            if ($order->kitchen) {
                $this->stationService->releaseKitchenCapacity($order->kitchen);
            }

            return $order;
        });
    }

    /**
     * Validate HACCP thermal seal integrity and food temperature safety.
     *
     * @return array{is_safe: bool, verdict: string}
     */
    public function auditHaccpThermalSeal(string $sealBarcode, float $tempCelsius, string $mealType = 'hot'): array
    {
        $barcodeIntact = str_starts_with($sealBarcode, 'SEAL-') && strlen($sealBarcode) >= 12;

        $tempSafe = ($mealType === 'hot')
            ? ($tempCelsius >= 60.0 && $tempCelsius <= 85.0)
            : ($tempCelsius >= 0.0 && $tempCelsius <= 4.0);

        $isSafe = $barcodeIntact && $tempSafe;

        return [
            'is_safe' => $isSafe,
            'verdict' => $isSafe ? 'HACCP_PASSED' : 'SAFETY_BREACH_FLAGGED',
        ];
    }

    /**
     * Calculate revenue split using Hamilton largest remainder apportionment (B0′.1).
     *
     * @param  array<string, int>  $sharesBps  Key => Basis points
     * @return array<string, int> Key => Apportioned integer IDR
     */
    public function calculateRevenueSplit(int $totalIdr, array $sharesBps): array
    {
        $items = [];
        foreach ($sharesBps as $key => $bps) {
            $items[] = ['key' => (string) $key, 'weight' => $bps];
        }

        $result = HamiltonApportionment::apportion($totalIdr, $items);

        return $result->allocations;
    }

    /**
     * Settle delivered order revenue into general ledger.
     */
    public function settleDeliveredOrderRevenue(int $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = KitchenOrder::findOrFail($orderId);
            $revenueAmount = (int) $order->meal_cost_idr;

            if ($revenueAmount <= 0) {
                return;
            }

            $this->ledgerService->post(new PostingDTO(
                type: 'catering_revenue_settlement',
                description: "Delivered Meal Revenue Settlement for Order {$order->order_code}",
                idempotencyKey: "ck:rev:settle:{$order->id}",
                entries: Posting::lines()
                    ->debit('hcm:meals_deduction:IDR', $revenueAmount)
                    ->credit('ckt:catering_revenue:IDR', $revenueAmount)
                    ->toEntries('IDR'),
                referenceType: 'catering_order',
                referenceId: (string) $order->id
            ));
        });
    }
}
