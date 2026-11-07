<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningContractorPaymentHold;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPlanShift;

/**
 * Ekspor CSV untuk setiap angka dashboard Fase 93; baris dihitung dari fakta tabel, urutan eksplisit.
 */
final class ExportMiningProductionCsvAction
{
    /**
     * @return array{header: list<string>, rows: list<list<int|string>>}
     */
    public function execute(string $type): array
    {
        return match ($type) {
            'operations' => [
                'header' => ['shift_date', 'shift_code', 'pit_id', 'target_kg', 'actual_kg', 'hours_nominal', 'hours_adjusted', 'rain_hours', 'maintenance_hours', 'variance_kg'],
                'rows' => MiningPlanShift::query()->orderBy('shift_date')->orderBy('shift_code')->orderBy('pit_id')->get()
                    ->map(fn ($s) => [$s->shift_date->format('Y-m-d'), $s->shift_code, $s->pit_id, $s->target_tonnage_kg, $s->actual_tonnage_kg,
                        $s->effective_hours_nominal, $s->effective_hours_adjusted, $s->rain_downtime_hours, $s->maintenance_downtime_hours, $s->variance_kg])->all(),
            ],
            'haul' => [
                'header' => ['shift_date', 'shift_code', 'truck_id', 'cycle_seq', 'status', 'target_payload_kg', 'actual_payload_kg', 'distance_m', 'fuel_ml', 'override'],
                'rows' => MiningHaulCycle::query()->orderBy('shift_date')->orderBy('truck_id')->orderBy('cycle_seq')->get()
                    ->map(fn ($c) => [$c->shift_date->format('Y-m-d'), $c->shift_code, $c->truck_id, $c->cycle_seq, $c->status->value,
                        $c->target_payload_kg, $c->actual_payload_kg, $c->distance_meters, $c->fuel_consumed_ml, (int) $c->is_override])->all(),
            ],
            'fuel' => [
                'header' => ['case_number', 'site_id', 'contractor', 'equipment_id', 'shift_date', 'actual_ml', 'baseline_ml', 'excess_bps', 'held_idr', 'status'],
                'rows' => MiningFuelAnomalyCase::query()->orderBy('shift_date')->orderBy('case_number')->get()
                    ->map(fn ($c) => [$c->case_number, $c->site_id, $c->contractor_party_ref, $c->equipment_id, $c->shift_date->format('Y-m-d'),
                        $c->actual_fuel_ml, $c->baseline_fuel_ml, $c->excess_bps, $c->held_amount_idr, $c->status->value])->all(),
            ],
            'holds' => [
                'header' => ['hold_reference', 'contractor', 'case_id', 'hold_idr', 'released_idr', 'deducted_idr', 'status'],
                'rows' => MiningContractorPaymentHold::query()->orderBy('hold_reference')->get()
                    ->map(fn ($h) => [$h->hold_reference, $h->contractor_party_ref, $h->case_id, $h->hold_amount_idr, $h->released_amount_idr, $h->deducted_amount_idr, $h->status])->all(),
            ],
            default => throw new InvalidArgumentException("Tipe laporan '{$type}' tidak dikenal."),
        };
    }
}
