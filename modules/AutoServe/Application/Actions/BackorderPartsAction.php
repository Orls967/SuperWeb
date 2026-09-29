<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use App\Models\User;
use Exception;
use Illuminate\Support\Str;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\OrderItem;

/**
 * Buat pesanan pembelian internal (backorder) ketika sparepart pada estimasi
 * yang disetujui ternyata stoknya kurang. Dibayar dari akun sistem bengkel,
 * bukan dari dompet customer.
 */
class BackorderPartsAction extends BaseAction
{
    private const EXPENSE_ACCOUNT = 'expense:autoserve:parts:IDR';

    private const SUPPLIER_ACCOUNT = 'clearing:external:IDR';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ResolveSparepartProductAction $resolveSparepartProduct,
        private readonly Ledger $ledger,
    ) {}

    /**
     * Hitung kekurangan stok untuk sebuah estimasi.
     *
     * @return array<int, array{sparepart: Sparepart, product_id: int, shortage: int, unit_price: int}>
     */
    public function shortages(Estimate $estimate): array
    {
        $shortages = [];

        foreach ($estimate->partItems() as $item) {
            if (empty($item['ref_id'])) {
                continue;
            }

            $sparepart = Sparepart::find($item['ref_id']);
            if ($sparepart === null) {
                continue;
            }

            $product = $this->resolveSparepartProduct->execute($sparepart);
            $available = $this->inventoryService->available($product->id);
            $needed = (int) $item['qty'];

            if ($available < $needed) {
                $shortages[] = [
                    'sparepart' => $sparepart,
                    'product_id' => $product->id,
                    'shortage' => $needed - $available,
                    'unit_price' => (int) $item['unit_price'],
                ];
            }
        }

        return $shortages;
    }

    /**
     * Buat order backorder internal untuk seluruh kekurangan stok estimasi.
     *
     * @param  array<int, array{sparepart: Sparepart, product_id: int, shortage: int, unit_price: int}>  $shortages
     */
    public function execute(Estimate $estimate, array $shortages): Order
    {
        if ($shortages === []) {
            throw new Exception('Tidak ada kekurangan stok yang perlu dipesan.');
        }

        return $this->transaction(function () use ($estimate, $shortages) {
            $estimate->loadMissing('booking');
            $operator = $this->workshopOperator($estimate);

            $subtotal = array_sum(array_map(
                fn (array $row) => $row['unit_price'] * $row['shortage'],
                $shortages
            ));

            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $operator->id,
                'status' => OrderStatus::PROCESSING,
                'subtotal' => $subtotal,
                'shipping_fee' => 0,
                'discount' => 0,
                'grand_total' => $subtotal,
                'shipping_address' => [
                    'name' => 'Gudang Bengkel AutoServe',
                    'phone' => '-',
                    'address' => 'Pembelian internal sparepart (backorder)',
                    'city' => '-',
                    'postal_code' => '-',
                ],
                'paid_at' => now(),
            ]);

            foreach ($shortages as $row) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $row['product_id'],
                    'name_snapshot' => $row['sparepart']->name,
                    'price_snapshot' => $row['unit_price'],
                    'qty' => $row['shortage'],
                    'line_total' => $row['unit_price'] * $row['shortage'],
                ]);
            }

            // Pembayaran ke pemasok: beban bengkel keluar ke pihak eksternal
            if ($subtotal > 0) {
                $this->ledger->post(new PostingDTO(
                    type: TransactionType::PAYMENT->value,
                    description: "Pembelian sparepart backorder {$order->number} untuk booking {$estimate->booking?->booking_code}",
                    idempotencyKey: 'backorder_'.$order->uuid,
                    entries: [
                        PostingEntryDTO::forCode(self::EXPENSE_ACCOUNT, 'IDR', -$subtotal),
                        PostingEntryDTO::forCode(self::SUPPLIER_ACCOUNT, 'IDR', $subtotal),
                    ],
                    referenceType: 'store_order',
                    referenceId: $order->id,
                    meta: [
                        'estimate_id' => $estimate->id,
                        'booking_id' => $estimate->booking_id,
                    ],
                    createdBy: $operator->id,
                    postedAt: now(),
                ));
            }

            $estimate->update(['backorder_order_id' => $order->id]);

            return $order->fresh('items');
        });
    }

    /**
     * Sparepart backorder tiba: stok masuk dan order ditutup.
     */
    public function receive(Order $order): Order
    {
        return $this->transaction(function () use ($order) {
            if ($order->status === OrderStatus::COMPLETED) {
                throw new Exception('Backorder ini sudah diterima sebelumnya.');
            }

            foreach ($order->items as $item) {
                $this->inventoryService->adjust(
                    $item->product_id,
                    $item->qty,
                    StockMovementReason::PURCHASE,
                    'store_order',
                    $order->id,
                    "Penerimaan backorder {$order->number}",
                    $order->user_id
                );

                $product = $item->product?->fresh();
                if ($product && $product->productable_type === 'serve_sparepart') {
                    Sparepart::where('id', $product->productable_id)
                        ->update(['stock' => $product->cached_stock]);
                }
            }

            $order->update(['status' => OrderStatus::COMPLETED]);

            return $order->fresh('items');
        });
    }

    /**
     * Akun operasional bengkel diwakili admin; jika tidak ada, mekanik penanggung jawab.
     */
    private function workshopOperator(Estimate $estimate): User
    {
        $admin = User::where('role', 'admin')->orderBy('id')->first();

        if ($admin !== null) {
            return $admin;
        }

        $mechanicId = $estimate->booking?->mechanic_id ?? $estimate->created_by;
        $mechanic = $mechanicId ? User::find($mechanicId) : null;

        if ($mechanic === null) {
            throw new Exception('Tidak ada akun operasional bengkel untuk mencatat backorder.');
        }

        return $mechanic;
    }
}
