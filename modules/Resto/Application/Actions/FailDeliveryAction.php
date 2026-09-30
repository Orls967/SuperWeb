<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Resto\Domain\Enums\DeliveryStatus;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Models\Delivery;

class FailDeliveryAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function handle(
        Delivery $delivery,
        string $failureReason,
        bool $refundFoodOnly = true
    ): Delivery {
        return DB::transaction(function () use ($delivery, $failureReason, $refundFoodOnly) {
            $lockedDelivery = Delivery::where('id', $delivery->id)->lockForUpdate()->firstOrFail();
            $order = $lockedDelivery->order;

            $lockedDelivery->status = DeliveryStatus::FAILED;
            $lockedDelivery->failure_reason = $failureReason;
            $lockedDelivery->save();

            // Lakukan refund jika order sudah dibayar dan memiliki pelanggan
            if ($order && $order->status === OrderStatus::PAID && $order->customer_id) {
                $customerId = $order->customer_id;
                $outlet = $lockedDelivery->outlet ?: $order->outlet;
                $outletCode = $outlet->code ?: "OUT-{$outlet->id}";

                // Sesuai SOP: jika driver gagal kirim, kembalikan harga makanan (ongkir hangus sebagai kompensasi kurir)
                $refundAmount = $refundFoodOnly
                    ? ($order->subtotal + $order->tax_pb1 + $order->service_charge + $order->rounding)
                    : $order->grand_total;

                if ($refundAmount > 0) {
                    $walletAccCode = "wallet:user:{$customerId}:IDR";
                    $foodRevAcc = "revenue:resto:{$outletCode}:food";

                    $this->ensureLedgerAccountExists($walletAccCode, "Dompet Pelanggan #{$customerId}", AccountKind::WALLET);
                    $this->ensureLedgerAccountExists($foodRevAcc, "Pendapatan Makanan Resto {$outlet->name}", AccountKind::REVENUE);

                    $refundBd = BigDecimal::of($refundAmount);

                    $this->ledger->post(new PostingDTO(
                        type: TransactionType::REFUND->value,
                        description: "Refund parsial makanan untuk delivery gagal #{$order->number}: {$failureReason}",
                        idempotencyKey: "resto:dlv:fail_refund:{$lockedDelivery->id}:".Str::uuid(),
                        entries: [
                            PostingEntryDTO::forCode($foodRevAcc, 'IDR', $refundBd->negated()),
                            PostingEntryDTO::forCode($walletAccCode, 'IDR', $refundBd),
                        ],
                        referenceType: 'resto_delivery',
                        referenceId: $lockedDelivery->id,
                        createdBy: auth()->id()
                    ));

                    $order->update([
                        'status' => OrderStatus::REFUNDED,
                        'void_reason' => "Delivery gagal: {$failureReason}",
                    ]);
                }
            }

            return $lockedDelivery;
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
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
