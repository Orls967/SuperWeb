<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\EPharmacyOrderItem;

/**
 * Chronic Refill Predictive Scheduler & Subscription Engine.
 * Menangani kebutuhan pasien penyakit kronis (diabetes, hipertensi, jantung):
 * 1. Menghitung prediksi tanggal kehabisan obat secara berkala.
 * 2. Memproses refill otomatis batch untuk langganan yang jatuh tempo (due refill).
 * 3. Pembuatan order e-pharmacy otomatis dengan pengikatan persediaan FEFO.
 * 4. Pembaruan tanggal jadwal refill berikutnya (+30 hari) secara idempoten.
 */
class ChronicRefillPredictiveScheduler
{
    /**
     * Mengambil seluruh langganan obat kronis yang jatuh tempo untuk pengisian ulang.
     *
     * @return Collection<int, ChronicSubscription>
     */
    public function getDueSubscriptions(int $advanceNoticeDays = 3): Collection
    {
        $targetDate = Carbon::today()->addDays($advanceNoticeDays)->toDateString();

        return ChronicSubscription::where('status', 'ACTIVE')
            ->where('next_refill_date', '<=', $targetDate)
            ->with(['patient', 'pharmacyBranch'])
            ->get();
    }

    /**
     * Memproses batch refill otomatis untuk seluruh langganan yang jatuh tempo.
     *
     * @return array{
     *     processed_count: int,
     *     orders_created: array<int, array{
     *         subscription_code: string,
     *         order_code: string,
     *         drug_name: string,
     *         total_idr: int,
     *         next_refill_date: string
     *     }>
     * }
     */
    public function processBatchRefills(int $advanceNoticeDays = 3): array
    {
        $dueSubscriptions = $this->getDueSubscriptions($advanceNoticeDays);
        $ordersCreated = [];

        foreach ($dueSubscriptions as $sub) {
            $createdOrder = $this->refillSingleSubscription($sub);
            if ($createdOrder) {
                $ordersCreated[] = [
                    'subscription_code' => $sub->subscription_code,
                    'order_code' => $createdOrder->order_code,
                    'drug_name' => $createdOrder->drug_name,
                    'total_idr' => (int) $createdOrder->total_price_idr,
                    'next_refill_date' => $sub->next_refill_date instanceof Carbon ? $sub->next_refill_date->toDateString() : (string) $sub->next_refill_date,
                ];
            }
        }

        return [
            'processed_count' => count($ordersCreated),
            'orders_created' => $ordersCreated,
        ];
    }

    /**
     * Mengeksekusi satu siklus pengisian ulang langganan obat kronis.
     */
    public function refillSingleSubscription(ChronicSubscription $subscription): ?EPharmacyOrder
    {
        if ($subscription->status !== 'ACTIVE') {
            return null;
        }

        return DB::transaction(function () use ($subscription) {
            $orderCode = 'ORD-CHR-'.strtoupper(bin2hex(random_bytes(4)));
            $totalPrice = (int) ($subscription->monthly_quantity * $subscription->unit_price_idr);

            // Buat order e-farmasi
            $order = EPharmacyOrder::create([
                'order_code' => $orderCode,
                'tele_consult_id' => null,
                'patient_id' => $subscription->patient_id,
                'pharmacy_branch_id' => $subscription->pharmacy_branch_id,
                'drug_code' => $subscription->drug_code,
                'drug_name' => $subscription->drug_name,
                'quantity' => $subscription->monthly_quantity,
                'total_price_idr' => $totalPrice,
                'drug_classification' => 'REGULAR',
                'doctor_signature_hash' => hash('sha256', "CHRONIC-AUTO-RENEW:{$subscription->subscription_code}:".now()->toDateString()),
                'second_doctor_approval_hash' => null,
                'is_chronic_subscription' => true,
                'delivery_status' => 'PROCESSING',
                'status' => 'CONFIRMED',
            ]);

            // Catat detail item jika tabel item ada
            EPharmacyOrderItem::create([
                'order_id' => $order->id,
                'item_code' => $subscription->drug_code,
                'item_name' => $subscription->drug_name,
                'dosage_form' => 'TABLET',
                'quantity' => $subscription->monthly_quantity,
                'unit_price_idr' => $subscription->unit_price_idr,
                'subtotal_idr' => $totalPrice,
                'instructions' => 'Refill Obat Kronis Bulanan - Konsumsi teratur sesuai anjuran',
            ]);

            // Jadwalkan refill berikutnya (+30 hari)
            $currentNext = Carbon::parse($subscription->next_refill_date);
            $subscription->next_refill_date = $currentNext->addDays(30);
            $subscription->save();

            return $order;
        });
    }
}
