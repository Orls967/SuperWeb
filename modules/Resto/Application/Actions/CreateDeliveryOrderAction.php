<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Resto\Domain\Enums\DeliveryStatus;
use Modules\Resto\Domain\Enums\OrderChannel;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Models\Delivery;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\OrderItem;
use Modules\Resto\Domain\Models\Outlet;

class CreateDeliveryOrderAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly Ledger $ledger,
        private readonly VerifiesWalletPin $verifyPinAction
    ) {}

    /**
     * @param  array<int, array{menu_item_id: int, qty: int}>  $itemsData
     * @return array{order: Order, delivery: Delivery}
     */
    public function handle(
        int $outletId,
        array $itemsData,
        string $recipientName,
        string $recipientPhone,
        string $deliveryAddress,
        float $distanceKm = 1.0,
        ?User $customerUser = null,
        string $paymentMethod = 'wallet',
        ?string $pin = null
    ): array {
        if (empty($itemsData)) {
            throw new InvalidArgumentException('Pesanan delivery harus memiliki minimal 1 item.');
        }

        $outlet = Outlet::findOrFail($outletId);

        return DB::transaction(function () use (
            $outlet,
            $itemsData,
            $recipientName,
            $recipientPhone,
            $deliveryAddress,
            $distanceKm,
            $customerUser,
            $paymentMethod,
            $pin
        ) {
            $subtotalFood = 0;
            $totalPortions = 0;
            $orderItemsToCreate = [];

            foreach ($itemsData as $row) {
                $menuItem = MenuItem::findOrFail($row['menu_item_id']);
                $qty = (int) $row['qty'];
                if ($qty <= 0) {
                    continue;
                }

                // Gunakan takeaway_price bila ada, fallback ke base price
                $pricePerItem = $menuItem->takeaway_price ?? $menuItem->price;
                $lineTotal = $pricePerItem * $qty;

                $subtotalFood += $lineTotal;
                $totalPortions += $qty;

                $orderItemsToCreate[] = [
                    'menu_item' => $menuItem,
                    'qty' => $qty,
                    'unit_price' => $pricePerItem,
                    'line_total' => $lineTotal,
                ];
            }

            // 1. Biaya Antar Berjenjang per Radius KM (Rp10.000 untuk 3 km pertama, +Rp2.500/km berikutnya)
            $deliveryFee = 10000;
            if ($distanceKm > 3.0) {
                $extraKm = ceil($distanceKm - 3.0);
                $deliveryFee += (int) ($extraKm * 2500);
            }

            // 2. Biaya Kemasan Nasi Bungkus (Rp2.000 / bungkus)
            $packagingFee = $totalPortions * 2000;

            // 3. Potong Stok Bahan Kemasan via InventoryService
            $packagingIngredient = Ingredient::where('sku', 'ING-KERTAS-BUNGKUS')->first()
                ?? Ingredient::where('sku', 'ING-KOTAK-KATERING')->first()
                ?? Ingredient::where('category', 'kemasan')->first();

            if ($packagingIngredient) {
                $this->inventoryService->deductIngredient(
                    ingredientId: $packagingIngredient->id,
                    outletId: $outlet->id,
                    qtyBaseUnit: (string) $totalPortions,
                    reason: StockMovementReason::SALE,
                    sourceType: 'resto_delivery',
                    sourceId: 0,
                    note: "Kemasan bungkus delivery pesanan {$recipientName}",
                    userId: $customerUser?->id
                );
            }

            // 4. Pajak Restoran PB1 10% atas makanan
            $pb1 = (int) round($subtotalFood * 0.10);

            // 5. Total mentah & Pembulatan Rp100 terdekat
            $rawTotal = $subtotalFood + $pb1 + $deliveryFee + $packagingFee;
            $grandTotal = (int) (round($rawTotal / 100) * 100);
            $rounding = $grandTotal - $rawTotal;

            $dateStr = date('Ymd');
            $randomCode = strtoupper(Str::random(4));
            $orderNumber = "ORD-RSR-{$outlet->code}-DLV-{$dateStr}-{$randomCode}";

            // 6. Buat Record Order
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'number' => $orderNumber,
                'table_session_id' => null,
                'channel' => OrderChannel::DELIVERY,
                'customer_id' => $customerUser?->id,
                'guest_name' => $recipientName,
                'subtotal' => $subtotalFood,
                'discount' => 0,
                'service_charge' => $packagingFee, // Alokasi biaya kemasan
                'tax_pb1' => $pb1,
                'rounding' => $rounding,
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'status' => OrderStatus::AWAITING_PAYMENT,
                'idempotency_key' => "order_dlv_{$orderNumber}",
            ]);

            foreach ($orderItemsToCreate as $oi) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $oi['menu_item']->id,
                    'tray_id' => null,
                    'name_snapshot' => $oi['menu_item']->name,
                    'unit_price_snapshot' => $oi['unit_price'],
                    'qty' => $oi['qty'],
                    'line_total' => $oi['line_total'],
                    'consumed_state' => 'consumed',
                ]);
            }

            // 7. Buat Record Delivery
            $delivery = Delivery::create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'outlet_id' => $outlet->id,
                'delivery_type' => 'delivery',
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'delivery_address' => $deliveryAddress,
                'distance_km' => (string) $distanceKm,
                'delivery_fee' => $deliveryFee,
                'packaging_fee' => $packagingFee,
                'status' => DeliveryStatus::PREPARING,
            ]);

            // 8. Bayar via Wallet jika metode pembayaran wallet
            if ($paymentMethod === 'wallet' && $customerUser) {
                if ($pin !== null) {
                    $this->verifyPinAction->handle($customerUser, $pin);
                }

                $walletAccount = $customerUser->walletAccount('IDR');
                $walletAccCode = $walletAccount->code;
                $walletAccount = LedgerAccount::where('code', $walletAccCode)->first();
                if (! $walletAccount || ! $walletAccount->canCover($grandTotal)) {
                    throw new InsufficientFundsException('Saldo dompet tidak mencukupi untuk pembayaran delivery Rp '.number_format($grandTotal, 0, ',', '.'));
                }

                $outletCode = $outlet->code ?: "OUT-{$outlet->id}";
                $foodRevAcc = "revenue:resto:{$outletCode}:food";
                $dlvRevAcc = "revenue:resto:{$outletCode}:delivery";

                $this->ensureLedgerAccountExists($foodRevAcc, "Pendapatan Makanan Resto {$outlet->name}", AccountKind::REVENUE);
                $this->ensureLedgerAccountExists($dlvRevAcc, "Pendapatan Ongkir Resto {$outlet->name}", AccountKind::REVENUE);

                $foodShare = $subtotalFood + $pb1 + $packagingFee + $rounding;
                $deliveryShare = $deliveryFee;

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::PAYMENT->value,
                    description: "Pembayaran order delivery #{$order->number}",
                    idempotencyKey: "resto:dlv:pay:{$order->id}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($walletAccCode, 'IDR', BigDecimal::of($grandTotal)->negated()),
                        PostingEntryDTO::forCode($foodRevAcc, 'IDR', BigDecimal::of($foodShare)),
                        PostingEntryDTO::forCode($dlvRevAcc, 'IDR', BigDecimal::of($deliveryShare)),
                    ],
                    referenceType: 'resto_order',
                    referenceId: $order->id,
                    createdBy: $customerUser->id
                ));

                $order->update([
                    'status' => OrderStatus::PAID,
                    'paid_at' => now(),
                ]);
            }

            return ['order' => $order, 'delivery' => $delivery];
        });
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
