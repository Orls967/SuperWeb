<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Events\EgyMeterReadV1;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Numerics\MeterDelta;

class IngestSmartGridMeterTelemetryAction extends BaseAction
{
    /**
     * @param array{
     *     meter_number: string,
     *     reading_at?: string,
     *     cumulative_wh: int,
     *     idempotency_key?: string,
     *     is_meter_reset?: bool
     * } $data
     */
    public function execute(array $data): MeterReading
    {
        $meterNumber = $data['meter_number'] ?? '';
        $meter = SmartMeter::where('meter_serial_number', $meterNumber)->firstOrFail();

        $readingAt = isset($data['reading_at'])
            ? CarbonImmutable::parse($data['reading_at'])
            : CarbonImmutable::now();

        $cumulativeWh = (int) ($data['cumulative_wh'] ?? 0);
        $isMeterReset = (bool) ($data['is_meter_reset'] ?? false);

        // Check idempotency: unique on (meter_id, reading_at)
        $existing = MeterReading::where('meter_id', $meter->id)
            ->where('reading_at', $readingAt)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($meter, $readingAt, $cumulativeWh, $isMeterReset, $data) {
            $prevReading = MeterReading::where('meter_id', $meter->id)
                ->where('reading_at', '<', $readingAt)
                ->orderByDesc('reading_at')
                ->first();

            $status = 'OK';
            if (isset($data['delta_wh'])) {
                $deltaWh = (int) $data['delta_wh'];
                $status = 'OK';
            } elseif (isset($data['kwh_consumed'])) {
                $deltaWh = (int) ($data['kwh_consumed'] * 1000);
                $status = 'OK';
            } elseif ($isMeterReset) {
                $deltaWh = $cumulativeWh;
                $status = 'RESET';
            } elseif ($prevReading === null) {
                $deltaWh = 0;
                $status = 'OK';
            } else {
                $prevWh = (int) $prevReading->cumulative_wh;
                $modulus = $meter->modulus !== null ? (int) $meter->modulus : null;

                $deltaWh = MeterDelta::calculate($prevWh, $cumulativeWh, $modulus);

                if ($cumulativeWh < $prevWh && $modulus !== null) {
                    $status = 'ROLLOVER';
                } else {
                    $diffMinutes = $prevReading->reading_at ? (int) $prevReading->reading_at->diffInMinutes($readingAt) : 0;
                    if ($diffMinutes > 15) {
                        $status = 'MISSING_INTERVAL_PREV';
                    }
                }
            }

            // TOU Window determination (Asia/Jakarta)
            $localTime = $readingAt->setTimezone('Asia/Jakarta');
            $hour = (int) $localTime->format('G');
            $isWbp = ($hour >= 17 && $hour < 22);
            $ratePerKwh = $isWbp ? 2100 : 1450;

            $kwhConsumed = $deltaWh / 1000.0;
            $chargeMinor = (int) round(($deltaWh * $ratePerKwh) / 1000.0);

            $reading = MeterReading::create([
                'id' => (string) Str::uuid(),
                'meter_id' => $meter->id,
                'reading_at' => $readingAt,
                'cumulative_wh' => $cumulativeWh,
                'delta_wh' => $deltaWh,
                'interval_status' => $status,
                'is_wbp' => $isWbp,
                'kwh_consumed' => $kwhConsumed,
                'is_peak_hour' => $isWbp,
                'rate_per_kwh_minor' => $ratePerKwh,
                'total_charge_minor' => $chargeMinor,
                'interval_start' => $prevReading ? $prevReading->reading_at : $readingAt,
                'interval_end' => $readingAt,
            ]);

            if ($kwhConsumed > 0) {
                $meter->increment('total_kwh_accumulated', $kwhConsumed);
            }

            // Dispatch Spine event
            event(new EgyMeterReadV1(
                readingId: (string) $reading->id,
                meterId: (string) $meter->id,
                propertyId: (string) $meter->consumer_property_id,
                region: (string) ($meter->grid_node_id ?? 'JAMALI'),
                intervalWh: $deltaWh,
                readingAt: $readingAt->toIso8601String(),
            ));

            return $reading;
        });
    }
}
