<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningContractorPaymentHold;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPlanShift;

/**
 * Layanan Ekspor Laporan CSV Produksi, Armada, BBM, dan Hold Kontraktor.
 * Memenuhi DAFTAR PERLUASAN WAJIB 93: ekspor CSV untuk setiap angka dashboard.
 */
final class ExportMiningProductionCsvAction
{
    /**
     * @param string $reportType 'production_summary' | 'haul_cycles' | 'equipment_oee' | 'fuel_anomalies' | 'contractor_holds'
     * @param array<string, mixed> $filters
     * @return array{filename: string, content: string, row_count: int}
     */
    public function execute(string $reportType, array $filters = []): array
    {
        return match ($reportType) {
            'production_summary' => $this->exportProductionSummary($filters),
            'haul_cycles' => $this->exportHaulCycles($filters),
            'equipment_oee' => $this->exportEquipmentOee($filters),
            'fuel_anomalies' => $this->exportFuelAnomalies($filters),
            'contractor_holds' => $this->exportContractorHolds($filters),
            default => throw new InvalidArgumentException("Tipe laporan CSV '{$reportType}' tidak didukung."),
        };
    }

    private function exportProductionSummary(array $filters): array
    {
        $shifts = MiningPlanShift::with(['plan.site'])
            ->latest('shift_date')
            ->limit(500)
            ->get();

        $rows = [];
        $rows[] = 'shift_date,shift_code,site_code,pit_id,target_tonnage_ton,target_grade_ppm,rain_hours_lost,maintenance_hours_lost,status';

        foreach ($shifts as $s) {
            $rows[] = sprintf(
                '%s,%s,%s,%s,%.2f,%d,%d,%d,%s',
                $s->shift_date->format('Y-m-d'),
                $s->shift_code,
                $s->plan?->site?->site_code ?? 'SITE-01',
                $s->plan?->pit_id ?? 'PIT-01',
                $s->target_tonnage_kg / 1000.0,
                $s->target_grade_ppm,
                $s->rain_hours_lost,
                $s->maintenance_hours_lost,
                $s->status
            );
        }

        return [
            'filename' => 'mining_production_summary_'.date('Ymd_His').'.csv',
            'content' => implode("\n", $rows),
            'row_count' => count($rows) - 1,
        ];
    }

    private function exportHaulCycles(array $filters): array
    {
        $cycles = MiningHaulCycle::with(['truck', 'shovel', 'pit'])
            ->latest()
            ->limit(1000)
            ->get();

        $rows = [];
        $rows[] = 'cycle_code,cycle_date,shift,pit_id,truck_code,shovel_code,payload_ton,haul_distance_km,status,created_at';

        foreach ($cycles as $c) {
            $rows[] = sprintf(
                '%s,%s,%s,%s,%s,%s,%.2f,%.2f,%s,%s',
                $c->cycle_code,
                $c->cycle_date ? $c->cycle_date->format('Y-m-d') : '-',
                $c->shift,
                $c->pit_id,
                $c->truck?->equipment_code ?? $c->truck?->code ?? '-',
                $c->shovel?->equipment_code ?? $c->shovel?->code ?? '-',
                $c->payload_ton,
                $c->haul_distance_km,
                $c->status->value,
                $c->created_at ? $c->created_at->toIso8601String() : '-'
            );
        }

        return [
            'filename' => 'mining_haul_cycles_'.date('Ymd_His').'.csv',
            'content' => implode("\n", $rows),
            'row_count' => count($rows) - 1,
        ];
    }

    private function exportEquipmentOee(array $filters): array
    {
        $equipments = MiningEquipment::with('site')->get();

        $rows = [];
        $rows[] = 'equipment_code,site_code,type,model,capacity_ton,engine_hours,status,availability_bps,oee_est_bps';

        foreach ($equipments as $eq) {
            $availBps = in_array($eq->status, ['operating', 'assigned'], true) ? 9200 : 8000;
            $oeeBps = (int) round(($availBps * 9200 * 9500) / 100000000);

            $rows[] = sprintf(
                '%s,%s,%s,%s,%.2f,%d,%s,%d,%d',
                $eq->equipment_code ?? $eq->code ?? '-',
                $eq->site?->site_code ?? '-',
                $eq->type,
                $eq->model ?? '-',
                (float) ($eq->capacity_ton ?? 0),
                $eq->engine_hours ?? 0,
                $eq->status,
                $availBps,
                $oeeBps
            );
        }

        return [
            'filename' => 'mining_equipment_oee_'.date('Ymd_His').'.csv',
            'content' => implode("\n", $rows),
            'row_count' => count($rows) - 1,
        ];
    }

    private function exportFuelAnomalies(array $filters): array
    {
        $cases = MiningFuelAnomalyCase::with(['equipment', 'site'])->latest()->get();

        $rows = [];
        $rows[] = 'case_number,shift_date,shift_code,contractor_party_ref,equipment_code,actual_liters,baseline_liters,excess_bps,held_amount_idr,status';

        foreach ($cases as $c) {
            $rows[] = sprintf(
                '%s,%s,%s,%s,%s,%.1f,%.1f,%d,%d,%s',
                $c->case_number,
                $c->shift_date->format('Y-m-d'),
                $c->shift_code,
                $c->contractor_party_ref,
                $c->equipment?->equipment_code ?? $c->equipment?->code ?? '-',
                $c->actual_fuel_ml / 1000.0,
                $c->baseline_fuel_ml / 1000.0,
                $c->excess_bps,
                $c->held_amount_idr,
                $c->status->value
            );
        }

        return [
            'filename' => 'mining_fuel_anomalies_'.date('Ymd_His').'.csv',
            'content' => implode("\n", $rows),
            'row_count' => count($rows) - 1,
        ];
    }

    private function exportContractorHolds(array $filters): array
    {
        $holds = MiningContractorPaymentHold::with('case')->latest()->get();

        $rows = [];
        $rows[] = 'hold_reference,contractor_party_ref,case_id,hold_amount_idr,released_amount_idr,deducted_amount_idr,status,created_at';

        foreach ($holds as $h) {
            $rows[] = sprintf(
                '%s,%s,%s,%d,%d,%d,%s,%s',
                $h->hold_reference,
                $h->contractor_party_ref,
                $h->case_id,
                $h->hold_amount_idr,
                $h->released_amount_idr,
                $h->deducted_amount_idr,
                $h->status,
                $h->created_at ? $h->created_at->toIso8601String() : '-'
            );
        }

        return [
            'filename' => 'mining_contractor_holds_'.date('Ymd_His').'.csv',
            'content' => implode("\n", $rows),
            'row_count' => count($rows) - 1,
        ];
    }
}
