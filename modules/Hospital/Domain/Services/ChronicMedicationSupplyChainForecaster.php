<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\ChronicSubscription;

/**
 * Chronic Medication Supply Chain Forecaster & Procurement Buffer Planner.
 * Meramalkan kebutuhan pengadaan obat penyakit tidak menular (PTM):
 * 1. Agregasi permintaan bulanan terikat dari seluruh langganan obat kronis aktif.
 * 2. Penambahan *safety stock buffer* (20%) untuk mitigasi risiko *supply chain disruption*.
 * 3. Kalkulasi perkiraan anggaran belanja obat (procurement budget IDR) untuk peramalan arus kas.
 */
class ChronicMedicationSupplyChainForecaster
{
    private const SAFETY_BUFFER_RATIO = 1.20; // 20% safety stock buffer

    /**
     * Menghasilkan peramalan kebutuhan obat kronis selama periode N bulan ke depan.
     *
     * @return array<string, array{
     *     drug_code: string,
     *     drug_name: string,
     *     active_subscribers: int,
     *     base_monthly_quantity: int,
     *     forecast_total_with_safety_buffer: int,
     *     estimated_procurement_cost_idr: int
     * }>
     */
    public function forecastDemand(int $horizonMonths = 3): array
    {
        $activeSubs = ChronicSubscription::where('status', 'ACTIVE')->get();
        $grouped = [];

        foreach ($activeSubs as $sub) {
            $code = $sub->drug_code;
            if (! isset($grouped[$code])) {
                $grouped[$code] = [
                    'drug_code' => $code,
                    'drug_name' => $sub->drug_name,
                    'active_subscribers' => 0,
                    'base_monthly_quantity' => 0,
                    'unit_price' => (int) $sub->unit_price_idr,
                ];
            }

            $grouped[$code]['active_subscribers']++;
            $grouped[$code]['base_monthly_quantity'] += (int) $sub->monthly_quantity;
        }

        $forecast = [];
        foreach ($grouped as $code => $data) {
            $baseTotal = $data['base_monthly_quantity'] * $horizonMonths;
            $bufferedTotal = (int) ceil($baseTotal * self::SAFETY_BUFFER_RATIO);
            $estimatedCost = (int) round($bufferedTotal * $data['unit_price'] * 0.70); // Estimasi HPP 70% dari harga jual

            $forecast[$code] = [
                'drug_code' => $code,
                'drug_name' => $data['drug_name'],
                'active_subscribers' => $data['active_subscribers'],
                'base_monthly_quantity' => $data['base_monthly_quantity'],
                'forecast_total_with_safety_buffer' => $bufferedTotal,
                'estimated_procurement_cost_idr' => $estimatedCost,
            ];
        }

        return $forecast;
    }
}
