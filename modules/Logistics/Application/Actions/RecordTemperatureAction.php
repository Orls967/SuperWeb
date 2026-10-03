<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ExceptionSeverity;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;
use Modules\Logistics\Domain\Models\TemperatureReading;

/**
 * Records a cold-chain temperature reading for a reefer shipment.
 * Automatically flags excursion if temperature exceeds package bounds.
 */
class RecordTemperatureAction
{
    public function execute(
        Shipment $shipment,
        int $tempC10,
        ?int $containerId = null,
        ?string $recordedBy = null,
    ): TemperatureReading {
        return DB::transaction(function () use ($shipment, $tempC10, $containerId, $recordedBy) {
            // Determine range from shipment packages (reefer items)
            $minC10 = null;
            $maxC10 = null;

            foreach ($shipment->packages as $pkg) {
                if ($pkg->temp_min_c10 !== null) {
                    $minC10 = $minC10 === null ? $pkg->temp_min_c10 : min($minC10, $pkg->temp_min_c10);
                }
                if ($pkg->temp_max_c10 !== null) {
                    $maxC10 = $maxC10 === null ? $pkg->temp_max_c10 : max($maxC10, $pkg->temp_max_c10);
                }
            }

            $excursion = false;
            if ($minC10 !== null && $tempC10 < $minC10) {
                $excursion = true;
            }
            if ($maxC10 !== null && $tempC10 > $maxC10) {
                $excursion = true;
            }

            if ($excursion) {
                ShipmentException::firstOrCreate(
                    ['dedupe_key' => "temp_excursion:{$shipment->id}"],
                    [
                        'shipment_id' => $shipment->id,
                        'type' => ExceptionType::Damaged,
                        'severity' => ExceptionSeverity::High,
                        'status' => ShipmentException::STATUS_OPEN,
                        'description' => "Penyimpangan suhu cold-chain: {$tempC10} (batas: {$minC10} s/d {$maxC10})",
                        'payload' => [
                            'temp_c10' => $tempC10,
                            'min_c10' => $minC10,
                            'max_c10' => $maxC10,
                        ],
                        'detected_at' => now(),
                    ]
                );
            }

            return TemperatureReading::create([
                'shipment_id' => $shipment->id,
                'container_id' => $containerId,
                'temp_c10' => $tempC10,
                'min_c10' => $minC10,
                'max_c10' => $maxC10,
                'excursion' => $excursion,
                'recorded_by' => $recordedBy,
                'recorded_at' => now(),
            ]);
        });
    }
}
