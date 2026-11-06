<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Events\EgyMeterReadV1;
use Modules\Shared\Application\BaseAction;

class IngestSmartGridMeterTelemetryAction extends BaseAction
{
    public function __construct(
        protected ?EventSpineInterface $eventSpine = null
    ) {}

    /**
     * @param  array{
     *     meter_id: string,
     *     interval_start: string,
     *     interval_end: string,
     *     consumption_kwh: int,
     *     production_kwh?: int,
     *     peak_kw?: int,
     *     power_factor_bps?: int,
     *     idempotency_key?: string
     * }  $params
     */
    public function execute(array $params): MeterReading
    {
        $meterIdentifier = (string) ($params['meter_id'] ?? $params['meter_number'] ?? '');
        $meter = SmartMeter::where('id', $meterIdentifier)
            ->orWhere('meter_code', $meterIdentifier)
            ->orWhere('meter_serial_number', $meterIdentifier)
            ->firstOrFail();

        $start = isset($params['interval_start']) ? Carbon::parse($params['interval_start']) : now()->subMinutes(15);
        $end = isset($params['interval_end']) ? Carbon::parse($params['interval_end']) : now();
        $consumptionKwh = (int) ($params['consumption_kwh'] ?? (($params['delta_wh'] ?? 0) / 1000));
        $productionKwh = (int) ($params['production_kwh'] ?? 0);
        $peakKw = (int) ($params['peak_kw'] ?? 0);
        $powerFactorBps = (int) ($params['power_factor_bps'] ?? 10000);
        $idempotencyKey = $params['idempotency_key'] ?? "INGEST-{$meter->id}-{$start->format('YmdHis')}";

        // Idempotency check: key or interval
        $existing = MeterReading::where('idempotency_key', $idempotencyKey)
            ->orWhere(function ($q) use ($meter, $start) {
                $q->where('meter_id', $meter->id)
                    ->where('interval_start', $start);
            })
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // Determine if reading falls in peak hour (17:00 - 22:00)
        $hour = (int) $start->format('H');
        $isPeak = ($hour >= 17 && $hour < 22);

        return DB::transaction(function () use (
            $meter,
            $start,
            $end,
            $consumptionKwh,
            $productionKwh,
            $peakKw,
            $powerFactorBps,
            $isPeak,
            $idempotencyKey
        ) {
            $reading = MeterReading::create([
                'meter_id' => $meter->id,
                'interval_start' => $start,
                'interval_end' => $end,
                'reading_at' => $start,
                'consumption_kwh' => $consumptionKwh,
                'production_kwh' => $productionKwh,
                'peak_kw' => $peakKw,
                'power_factor_bps' => $powerFactorBps,
                'is_peak_hour' => $isPeak,
                'is_wbp' => $isPeak,
                'kwh_consumed' => (float) $consumptionKwh,
                'rate_per_kwh_minor' => $isPeak ? 2100 : 1400,
                'total_charge_minor' => $consumptionKwh * ($isPeak ? 2100 : 1400),
                'idempotency_key' => $idempotencyKey,
            ]);

            $meter->increment('total_kwh_accumulated', $consumptionKwh);

            // Emit Spine Event after commit
            DB::afterCommit(function () use ($meter, $reading) {
                event(new EgyMeterReadV1(
                    readingId: (string) $reading->id,
                    meterId: (string) $meter->id,
                    propertyId: (string) ($meter->property_id ?? $meter->consumer_property_id ?? ''),
                    region: 'ID-WEST',
                    intervalWh: (int) (($reading->consumption_kwh ?? 0) * 1000),
                    readingAt: $reading->interval_start?->toIso8601String() ?? now()->toIso8601String(),
                    cumulativeWh: 0
                ));
            });

            return $reading;
        });
    }
}
