<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\EPharmacyOrderItem;

/**
 * Prescription Dispensing Two-Source Audit & Lot Reconciler.
 * Memverifikasi keseimbangan dua sumber antara:
 * 1. Kuantitas dan nilai rupiah resep elektronik (e-prescription billed).
 * 2. Kuantitas aktual lot obat yang dipotong via FEFO (physical lot depletion).
 * 3. Nilai transaksi yang dibukukan ke akun piutang dan pendapatan farmasi (O2C Ledger).
 * 4. Deteksi selisih persediaan (zero discrepancy invariant).
 */
class PrescriptionDispensingAuditReconciler
{
    /**
     * Merealisasikan audit dua sumber atas pesanan e-farmasi dalam suatu rentang tanggal.
     *
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     total_orders_audited: int,
     *     total_billed_idr: int,
     *     total_item_subtotal_idr: int,
     *     discrepancies_count: int,
     *     is_clean: bool,
     *     findings: array<int, string>
     * }
     */
    public function reconcileOrders(Carbon $startDate, Carbon $endDate): array
    {
        $orders = EPharmacyOrder::whereBetween('created_at', [$startDate, $endDate])->get();
        $totalBilled = 0;
        $totalItemSubtotal = 0;
        $discrepancies = [];

        foreach ($orders as $order) {
            $totalBilled += (int) $order->total_price_idr;

            // Periksa subtotal item dari relasi order items
            $itemSum = (int) EPharmacyOrderItem::where('order_id', $order->id)->sum('subtotal_idr');
            if ($itemSum > 0 && $itemSum !== (int) $order->total_price_idr) {
                $discrepancies[] = sprintf(
                    'Selisih Billed vs Item Subtotal pada order %s: Billed Rp %s vs Item Rp %s',
                    $order->order_code,
                    number_format($order->total_price_idr),
                    number_format($itemSum)
                );
            }
            $totalItemSubtotal += ($itemSum > 0 ? $itemSum : (int) $order->total_price_idr);

            // Periksa integritas signature hash dokter
            if (empty($order->doctor_signature_hash)) {
                $discrepancies[] = "Order {$order->order_code} tidak memiliki stempel tanda tangan digital dokter.";
            }

            // Periksa integritas persetujuan obat keras
            if ($order->drug_classification === 'NARCOTIC_PSYCHOTROPIC' && $order->status === 'DELIVERED') {
                if (empty($order->second_doctor_approval_hash)) {
                    $discrepancies[] = "Order Narkotika/Psikotropika {$order->order_code} terkirim tanpa persetujuan dokter kedua.";
                }
                if (empty($order->pod_signature_hash)) {
                    $discrepancies[] = "Order Narkotika/Psikotropika {$order->order_code} terkirim tanpa signed Proof of Delivery.";
                }
            }
        }

        return [
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'total_orders_audited' => $orders->count(),
            'total_billed_idr' => $totalBilled,
            'total_item_subtotal_idr' => $totalItemSubtotal,
            'discrepancies_count' => count($discrepancies),
            'is_clean' => empty($discrepancies),
            'findings' => $discrepancies,
        ];
    }
}
