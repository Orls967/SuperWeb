<?php

declare(strict_types=1);

namespace Modules\Circular\Console\Commands;

use Illuminate\Console\Command;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Circular\Domain\Models\CirOrder;
use Modules\Circular\Domain\Models\CirSettlement;
use Modules\Logistics\Contracts\Shipment as ShipmentContract;
use Modules\Manufacturing\Contracts\RecycledMaterialReceiver;

final class AuditCircularCommand extends Command
{
    protected $signature = 'cir:audit';

    protected $description = 'Audit dua sumber Circular Marketplace (D24-5: tonase terjual = dikirim = diterima K1, escrow balance, dan fee revenue)';

    public function handle(RecycledMaterialReceiver $materialReceiver): int
    {
        $violations = [];

        // 1. Invarian Tonase: Untuk setiap order selesai (received / settled),
        // tonase diterima pada order = tonase net_weight_grams di shipment reverse = quantity_grams di penerimaan K1
        $completedOrders = CirOrder::whereIn('status', ['received', 'settled'])->get();

        foreach ($completedOrders as $order) {
            if ($order->accepted_grams === null || $order->accepted_grams <= 0) {
                $violations[] = "Order {$order->order_number} berstatus {$order->status} namun accepted_grams kosong/0.";

                continue;
            }

            if ($order->reverse_shipment_id === null) {
                $violations[] = "Order {$order->order_number} berstatus {$order->status} tidak memiliki reverse_shipment_id.";
            } else {
                $shipment = ShipmentContract::find($order->reverse_shipment_id);
                if ($shipment === null) {
                    $violations[] = "Order {$order->order_number} merujuk ke reverse_shipment_id {$order->reverse_shipment_id} yang tidak ada.";
                } elseif ((int) ($shipment->net_weight_grams ?? 0) !== (int) $order->accepted_grams) {
                    $violations[] = "Ketidakcocokan tonase pada {$order->order_number}: order accepted_grams={$order->accepted_grams} vs shipment net_weight_grams=".($shipment->net_weight_grams ?? 'null');
                }
            }

            if ($order->plant_receipt_id === null) {
                $violations[] = "Order {$order->order_number} berstatus {$order->status} tidak memiliki plant_receipt_id.";
            } else {
                $receiptGrams = $materialReceiver->getReceiptQuantityGrams((int) $order->plant_receipt_id);
                if ($receiptGrams === null) {
                    $violations[] = "Order {$order->order_number} merujuk ke plant_receipt_id {$order->plant_receipt_id} yang tidak ada.";
                } elseif ($receiptGrams !== (int) $order->accepted_grams) {
                    $violations[] = "Ketidakcocokan tonase K1 pada {$order->order_number}: order accepted_grams={$order->accepted_grams} vs K1 quantity_grams={$receiptGrams}";
                }
            }
        }

        // 2. Invarian Escrow: Saldo akun escrow:payment:IDR = Σ sisa escrow order yang belum selesai
        $openOrders = CirOrder::whereIn('status', ['escrow_held', 'in_transit', 'received'])->get();
        $expectedEscrowBalance = 0;
        foreach ($openOrders as $openOrder) {
            $expectedEscrowBalance += max(0, (int) $openOrder->escrow_amount_idr - (int) $openOrder->refund_amount_idr);
        }

        $escrowAccount = LedgerAccount::where('code', 'escrow:payment:IDR')->first();
        $actualEscrowBalance = $escrowAccount !== null ? (int) $escrowAccount->cached_balance : 0;

        if ($escrowAccount !== null && $actualEscrowBalance !== $expectedEscrowBalance) {
            $violations[] = "Selisih saldo escrow:payment:IDR: buku besar={$actualEscrowBalance} vs order terbuka={$expectedEscrowBalance}";
        }

        // 3. Invarian Fee Revenue: Saldo akun cir:fee_revenue:IDR = Σ round_half_up(gross_amount_idr * fee_bps / 10000)
        $settlements = CirSettlement::all();
        $expectedFeeRevenue = 0;
        foreach ($settlements as $settlement) {
            $calcFee = (int) round(($settlement->gross_amount_idr * $settlement->fee_bps) / 10000, 0, PHP_ROUND_HALF_UP);
            if ((int) $settlement->fee_amount_idr !== $calcFee) {
                $violations[] = "Fee settlement ID {$settlement->id} untuk order #{$settlement->order_id} korup: tercatat={$settlement->fee_amount_idr} vs rumus={$calcFee}";
            }
            $expectedFeeRevenue += $calcFee;
        }

        $feeAccount = LedgerAccount::where('code', 'cir:fee_revenue:IDR')->first();
        $actualFeeBalance = $feeAccount !== null ? (int) $feeAccount->cached_balance : 0;

        if ($feeAccount !== null && $actualFeeBalance !== $expectedFeeRevenue) {
            $violations[] = "Selisih saldo cir:fee_revenue:IDR: buku besar={$actualFeeBalance} vs akumulasi settlement={$expectedFeeRevenue}";
        }

        if ($violations !== []) {
            foreach ($violations as $v) {
                $this->error($v);
            }

            return self::FAILURE;
        }

        $this->info('Audit Circular Marketplace (cir:audit) LULUS: 0 selisih tonase, escrow, dan fee revenue.');

        return self::SUCCESS;
    }
}
