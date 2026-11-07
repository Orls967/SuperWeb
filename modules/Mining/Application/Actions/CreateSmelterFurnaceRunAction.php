<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\FurnaceRunStatus;
use Modules\Mining\Domain\Models\MiningSmelterFurnace;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

final class CreateSmelterFurnaceRunAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(string $furnaceId, array $attributes): MiningSmelterFurnaceRun
    {
        $furnace = MiningSmelterFurnace::findOrFail($furnaceId);

        $oreFeedWmt = (float) ($attributes['ore_feed_wmt'] ?? 0.0);
        if ($oreFeedWmt <= 0.0) {
            throw new InvalidArgumentException('Umpan bijih basah (ore_feed_wmt) harus lebih besar dari 0 ton.');
        }

        $moisturePct = (float) ($attributes['ore_moisture_pct'] ?? 30.0);
        $oreFeedDmt = round($oreFeedWmt * (1.0 - ($moisturePct / 100.0)), 2);

        $runCode = $attributes['run_code'] ?? ('RUN-'.strtoupper($furnace->furnace_code).'-'.now()->format('YmdHis'));

        return MiningSmelterFurnaceRun::create([
            'furnace_id' => $furnace->id,
            'run_code' => $runCode,
            'batch_lot_number' => $attributes['batch_lot_number'] ?? ('LOT-'.now()->format('Ymd-H')),
            'ore_feed_wmt' => $oreFeedWmt,
            'ore_moisture_pct' => $moisturePct,
            'ore_feed_dmt' => $oreFeedDmt,
            'ore_ni_grade_pct' => (float) ($attributes['ore_ni_grade_pct'] ?? 1.80),
            'ore_fe_grade_pct' => (float) ($attributes['ore_fe_grade_pct'] ?? 18.50),
            'ore_sio2_mgo_ratio' => (float) ($attributes['ore_sio2_mgo_ratio'] ?? 1.95),
            'coal_reductant_tons' => (float) ($attributes['coal_reductant_tons'] ?? 0.0),
            'silica_flux_tons' => (float) ($attributes['silica_flux_tons'] ?? 0.0),
            'power_consumption_mwh' => (float) ($attributes['power_consumption_mwh'] ?? 0.0),
            'tapping_temperature_c' => (float) ($attributes['tapping_temperature_c'] ?? 1520.0),
            'metal_output_tons' => 0.0,
            'metal_recovery_rate_pct' => 0.0,
            'slag_output_tons' => 0.0,
            'started_at' => now(),
            'operator_in_charge' => $attributes['operator_in_charge'] ?? 'Smelter Shift Metallurgist',
            'hmac_signature' => hash('sha256', "RUN:{$runCode}:{$oreFeedWmt}"),
            'status' => FurnaceRunStatus::DRAFT->value,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }
}
