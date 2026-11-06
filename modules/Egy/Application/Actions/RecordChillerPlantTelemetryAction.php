<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Asset\Contracts\MaintenanceWorkOrderRequester;
use Modules\Egy\Domain\Models\ChilledWaterReading;
use Modules\Egy\Domain\Models\Chiller;
use Modules\Egy\Domain\Models\DistrictCoolingPlant;
use Modules\Shared\Application\BaseAction;

final class RecordChillerPlantTelemetryAction extends BaseAction
{
    public function __construct(
        private readonly MaintenanceWorkOrderRequester $workOrderRequester
    ) {}

    public function execute(
        int $plantId,
        int $chillerId,
        int $supplyTempCMilli,
        int $returnTempCMilli,
        int $flowRateLps,
        int $thermalKwhTh,
        int $electricKwh,
        string $idempotencyKey
    ): ChilledWaterReading {
        if ($plantId <= 0 || $chillerId <= 0 || $electricKwh <= 0) {
            throw new InvalidArgumentException('Parameter telemetri pendingin distrik tidak valid');
        }

        return DB::transaction(function () use (
            $plantId,
            $chillerId,
            $supplyTempCMilli,
            $returnTempCMilli,
            $flowRateLps,
            $thermalKwhTh,
            $electricKwh,
            $idempotencyKey
        ) {
            $plant = DistrictCoolingPlant::findOrFail($plantId);
            $chiller = Chiller::findOrFail($chillerId);

            $deltaTemp = $returnTempCMilli - $supplyTempCMilli;
            $copBps = (int) intdiv($thermalKwhTh * 100, $electricKwh);

            // Bila COP anjlok di bawah ambang efisiensi minimum (COP 3.5 = 350 bps)
            if ($copBps < 350) {
                $this->workOrderRequester->request(
                    assetId: $chiller->id,
                    reasonCode: 'CHILLER_COP_DROP',
                    description: "Penurunan koefisien performa chiller {$chiller->chiller_number}: COP {$copBps} bps (ambang batas 350 bps)",
                    idempotencyKey: $idempotencyKey
                );
            }

            return ChilledWaterReading::create([
                'plant_id' => $plant->id,
                'chiller_id' => $chiller->id,
                'supply_temp_c_milli' => $supplyTempCMilli,
                'return_temp_c_milli' => $returnTempCMilli,
                'delta_temp_c_milli' => $deltaTemp,
                'flow_rate_lps' => $flowRateLps,
                'thermal_kwh_th' => $thermalKwhTh,
                'electric_kwh' => $electricKwh,
                'cop_bps' => $copBps,
                'recorded_at' => Carbon::now(),
            ]);
        });
    }
}
