<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use App\Models\User;
use Exception;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\ValueObjects\Money;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\OrderItem;
use Modules\Store\Domain\Models\Product;

/**
 * Pembeli membeli mobil bekas milik pengguna lain. Dana ditahan di escrow
 * sampai kendaraan diserahkan dan pembeli mengonfirmasi penerimaan.
 */
class PurchaseC2cVehicleAction extends BaseAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly PaymentGateway $paymentGateway,
        private readonly VerifiesWalletPin $verifyPinAction,
    ) {}

    /**
     * @param  array{name: string, phone: string, address: string, city: string, postal_code: string}  $shippingAddress
     */
    public function execute(
        User $buyer,
        Product $product,
        array $shippingAddress,
        string $pin,
        ?string $idempotencyKey = null,
    ): Order {
        if (! $product->isC2c() || ! $product->is_listed) {
            throw new Exception('Listing mobil bekas ini sudah tidak tersedia.');
        }

        if ((int) $product->seller_id === (int) $buyer->id) {
            throw new Exception('Kamu tidak dapat membeli kendaraanmu sendiri.');
        }

        $vehicle = Vehicle::find($product->productable_id);
        if ($vehicle === null || (int) $vehicle->user_id !== (int) $product->seller_id) {
            throw new Exception('Kendaraan sudah tidak dimiliki oleh penjual. Listing dibatalkan.');
        }

        $price = (int) $product->price;

        /** @var Order|null $order */
        $order = null;
        /** @var int|null $reservationId */
        $reservationId = null;

        // 1. Buat order dan kunci unit kendaraan agar tidak diperebutkan pembeli lain
        $this->transaction(function () use ($buyer, $product, $shippingAddress, $price, &$order, &$reservationId) {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

            // Re-check di bawah lock: penjual bisa menarik listing di tengah jalan
            if (! $lockedProduct->is_listed || (int) $lockedProduct->cached_stock < 1) {
                throw new Exception('Listing mobil bekas ini sudah tidak tersedia.');
            }

            // Retry-safe: order C2C yang sama (pembeli + unit) belum boleh diproses ulang.
            $existing = Order::query()
                ->where('user_id', $buyer->id)
                ->whereHas('items', fn ($query) => $query->where('product_id', $lockedProduct->id))
                ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::REFUNDED->value])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $order = $existing;

                return;
            }

            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $buyer->id,
                'seller_id' => $lockedProduct->seller_id,
                'status' => OrderStatus::PENDING_PAYMENT,
                'subtotal' => $price,
                'shipping_fee' => 0, // serah terima langsung antara penjual dan pembeli
                'discount' => 0,
                'grand_total' => $price,
                'shipping_address' => $shippingAddress,
            ]);

            $movement = $this->inventoryService->reserve(
                $lockedProduct->id,
                1,
                Order::class,
                $order->id,
                "Reservasi unit C2C order {$order->number}",
                $buyer->id
            );

            $reservationId = $movement->id;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $lockedProduct->id,
                'name_snapshot' => $lockedProduct->name,
                'price_snapshot' => $price,
                'qty' => 1,
                'line_total' => $price,
                'reservation_id' => $movement->id,
            ]);
        });

        $fresh = $order->fresh(['items.product', 'paymentIntents']);

        // Kunci hold bersifat deterministik per order: retry memakai kunci yang sama
        // sehingga gateway mengembalikan intent lama, bukan membuat hold kedua.
        $idempotency = $idempotencyKey ?? 'order_'.$fresh->id;

        if ($reservationId === null) {
            $reservationId = $fresh->items->first()?->reservation_id;
        }

        // Retry: order sudah ada dengan dana sudah ditahan → cukup pastikan status.
        if ($fresh->status === OrderStatus::AWAITING_HANDOVER) {
            return $fresh;
        }

        if ($fresh->status === OrderStatus::CANCELLED || $fresh->status === OrderStatus::REFUNDED) {
            throw new Exception('Pembelian C2C sebelumnya dibatalkan. Silakan ulangi transaksi baru.');
        }

        $intent = $fresh->paymentIntents()
            ->whereIn('status', [PaymentIntentStatus::HELD->value, PaymentIntentStatus::PENDING->value])
            ->latest()
            ->first();

        if ($intent === null) {
            // 2. Verifikasi PIN dompet pembeli
            try {
                $this->verifyPinAction->execute($buyer, $pin);
            } catch (\Throwable $e) {
                $this->rollback($fresh, $reservationId, 'PIN salah: '.$e->getMessage());
                throw $e;
            }

            // 3. Tahan dana pembeli di escrow (tanpa expiry: pelepasan diatur alur C2C)
            try {
                $this->paymentGateway->hold(
                    $fresh,
                    Money::fromIdr($price),
                    'c2c_hold_'.$idempotency
                );
            } catch (\Throwable $e) {
                $this->rollback($fresh, $reservationId, 'Gagal menahan dana: '.$e->getMessage());
                throw $e;
            }
        }

        // 4. Status akhir di bawah lock agar tidak meninggalkan order terbengkalai
        //    bila proses mati di tengah jalan (dana sudah tertahan).
        return $this->transaction(function () use ($fresh) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($fresh->id);

            if (in_array($lockedOrder->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
                throw new Exception('Pembelian C2C ini telah dibatalkan.');
            }

            if ($lockedOrder->status === OrderStatus::PENDING_PAYMENT) {
                $lockedOrder->update(['status' => OrderStatus::AWAITING_HANDOVER]);
            }

            return $lockedOrder->fresh(['items.product', 'paymentIntents']);
        });
    }

    private function rollback(Order $order, ?int $reservationId, string $reason): void
    {
        if ($reservationId !== null) {
            try {
                $this->inventoryService->release($reservationId, "Pembatalan C2C: {$reason}");
            } catch (\Throwable) {
                // reservasi mungkin sudah dilepas; abaikan
            }
        }

        $order->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
