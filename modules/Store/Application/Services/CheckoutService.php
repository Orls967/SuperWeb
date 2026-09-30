<?php

declare(strict_types=1);

namespace Modules\Store\Application\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Mall\Contracts\LoyaltyLedger;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\OrderItem;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly InventoryService $inventoryService,
        private readonly PaymentGateway $paymentGateway,
        private readonly VerifiesWalletPin $verifyPinAction,
        private readonly ?LoyaltyLedger $loyaltyLedger = null
    ) {}

    /**
     * Execute full checkout: reserve inventory -> charge wallet -> commit & fulfill or rollback.
     *
     * @param  array{name: string, phone: string, address: string, city: string, postal_code: string}  $shippingAddress
     */
    public function checkout(
        User $user,
        array $shippingAddress,
        string $pin,
        ?string $idempotencyKey = null,
        ?string $mallVoucherCode = null,
        ?int $redeemPoints = null
    ): Order {
        $cart = $this->cartService->getOrCreateCart($user);
        $cartItems = $cart->items()->with('product')->get();

        if ($cartItems->isEmpty()) {
            throw new Exception('Keranjang belanja kosong.');
        }

        // Calculate subtotal and shipping
        $subtotal = 0;
        $totalWeightGram = 0;
        $hasCar = false;

        foreach ($cartItems as $item) {
            $subtotal += $item->line_total;
            $totalWeightGram += ($item->product?->weight_gram ?? 500) * $item->qty;
            if ($item->product?->is_car) {
                $hasCar = true;
            }
        }

        // Flat shipping calculation
        $shippingFee = $hasCar
            ? 500000 // Rp 500.000 flat handling/towing for cars
            : max(15000, (int) (ceil($totalWeightGram / 1000) * 10000)); // Rp 10.000 / kg (min 15k)

        $discount = 0;
        $idempotency = $idempotencyKey ?? (string) Str::uuid();

        // Terapkan Voucher Mall bila diberikan
        if ($mallVoucherCode && $this->loyaltyLedger) {
            $vDisc = $this->loyaltyLedger->applyVoucher(
                voucherCode: $mallVoucherCode,
                tenantExternalRef: 'AUTOSERVE-DM',
                spendAmount: $subtotal,
                transactionRef: $idempotency
            );
            $discount += $vDisc;
        }

        // Tukar Duta Points bila diminta
        if ($redeemPoints && $this->loyaltyLedger) {
            $pDisc = $this->loyaltyLedger->redeemPointsForDiscount(
                user: $user,
                pointsToRedeem: $redeemPoints,
                description: 'Diskon Belanja AutoServe Store',
                referenceId: $idempotency
            );
            $discount += $pDisc;
        }

        $grandTotal = max(0, $subtotal + $shippingFee - $discount);

        // 1. Create order & reserve stock
        /** @var Order $order */
        $order = null;
        $reservations = [];

        DB::transaction(function () use (
            $user,
            $shippingAddress,
            $subtotal,
            $shippingFee,
            $discount,
            $grandTotal,
            $cartItems,
            &$order,
            &$reservations
        ) {
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'status' => OrderStatus::PENDING_PAYMENT,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'shipping_address' => $shippingAddress,
            ]);

            foreach ($cartItems as $item) {
                // Reserve stock with lock
                $movement = $this->inventoryService->reserve(
                    $item->product_id,
                    $item->qty,
                    Order::class,
                    $order->id,
                    "Reservasi stok checkout order {$order->number}",
                    $user->id
                );

                $reservations[] = $movement->id;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'name_snapshot' => $item->product->name,
                    'price_snapshot' => $item->price_snapshot ?: $item->product->price,
                    'qty' => $item->qty,
                    'line_total' => $item->line_total,
                    'reservation_id' => $movement->id,
                ]);
            }
        });

        // 2. Verify PIN
        try {
            $this->verifyPinAction->execute($user, $pin);
        } catch (\Throwable $e) {
            // Release reservations on invalid PIN
            $this->rollbackReservations($reservations, $order, 'PIN salah: '.$e->getMessage());
            throw $e;
        }

        // 3. Charge via PaymentGateway
        try {
            $this->paymentGateway->charge($order, $idempotency);
            $this->cartService->clearCart($user);

            // Berikan Duta Points atas belanja di Store
            if ($this->loyaltyLedger && $order->grand_total >= 10_000) {
                $this->loyaltyLedger->awardPoints(
                    user: $user,
                    spendAmount: (int) $order->grand_total,
                    receiptNumber: $order->number ?: "ORD-{$order->id}",
                    tenantExternalRef: 'AUTOSERVE-DM'
                );
            }

            return $order->fresh(['items.product', 'paymentIntents']);
        } catch (\Throwable $e) {
            // Release reservations on payment failure (e.g. insufficient funds)
            $this->rollbackReservations($reservations, $order, 'Pembayaran gagal: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Release all reservations and mark order as cancelled.
     *
     * @param  array<int>  $reservations
     */
    private function rollbackReservations(array $reservations, Order $order, string $reason): void
    {
        foreach ($reservations as $reservationId) {
            try {
                $this->inventoryService->release($reservationId, "Checkout rollback: {$reason}");
            } catch (\Throwable) {
                // Continue releasing others even if one fails
            }
        }

        $order->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
