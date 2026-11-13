<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

final class ControlTowerEventCatalogService
{
    /**
     * @return list<array{code: string, category: string, description: string}>
     */
    public function catalog(): array
    {
        return [
            ['code' => 'SCT_SOP_INITIATED', 'category' => 'sop', 'description' => 'Siklus S&OP bulanan dimulai pada status draft.'],
            ['code' => 'SCT_SOP_TRANSITIONED', 'category' => 'sop', 'description' => 'Transisi tahap S&OP tercatat dengan jejak audit aktor.'],
            ['code' => 'SCT_FORECAST_OVERRIDDEN', 'category' => 'forecast', 'description' => 'Override kolaboratif tim penjualan dengan alasan wajib.'],
            ['code' => 'SCT_PROMISE_EVALUATED', 'category' => 'atp_ctp', 'description' => 'Evaluasi janji pesanan ATP/CTP tersertifikasi.'],
            ['code' => 'SCT_DISRUPTION_BLAST_ANALYZED', 'category' => 'disruption', 'description' => 'Analisis radius dampak gangguan rantai pasok.'],
            ['code' => 'SCT_TWIN_SCENARIO_COMPLETED', 'category' => 'twin', 'description' => 'Simulasi What-If Digital Twin sandbox selesai.'],
            ['code' => 'SCT_SAFETY_STOCK_RECALCULATED', 'category' => 'inventory', 'description' => 'Kebijakan stok pengaman dinamis ABC/XYZ diperbarui.'],
            ['code' => 'SCT_KPI_SNAPSHOT_CAPTURED', 'category' => 'kpi', 'description' => 'Snapshot metrik OTIF, C2C, dan DOS direkam.'],
        ];
    }
}
