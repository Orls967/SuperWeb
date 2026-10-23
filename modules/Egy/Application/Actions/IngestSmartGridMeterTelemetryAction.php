<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Shared\Application\BaseAction;

class IngestSmartGridMeterTelemetryAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): MeterReading
    {
        $meterNumber = (string) $payload['meter_number'];
        $meter = SmartMeter::where('meter_serial_number', $meterNumber)->firstOrFail();

        return DB::transaction(function () use ($payload, $meter) {
            $kwh = (int) ($payload['kwh_consumed'] ?? 0);
            $rate = (int) ($payload['rate_per_kwh_minor'] ?? 1450);
            $totalCharge = $kwh * $rate;
            $start = $payload['interval_start'] ?? Carbon::now()->subMinutes(15);
            $end = $payload['interval_end'] ?? Carbon::now();

            $reading = MeterReading::create([
                'id' => (string) Str::uuid(),
                'meter_id' => $meter->id,
                'kwh_consumed' => $kwh,
                'is_peak_hour' => (bool) ($payload['is_peak_hour'] ?? false),
                'rate_per_kwh_minor' => $rate,
                'total_charge_minor' => $totalCharge,
                'interval_start' => $start,
                'interval_end' => $end,
            ]);

            $meter->increment('total_kwh_accumulated', $kwh);

            $this->audit(
                action: 'egy_meter_telemetry_ingested',
                auditable: $reading,
                newValues: ['meter_serial' => $meter->meter_serial_number, 'kwh' => $kwh],
                impactType: 'iot_telemetry',
            );

            return $reading;
        });
    }
}
