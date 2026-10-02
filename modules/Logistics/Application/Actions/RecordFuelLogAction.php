<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Exceptions\FuelLogException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\FuelLog;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Services\FuelConsumptionAnalyzer;

class RecordFuelLogAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger,
        private readonly FuelConsumptionAnalyzer $analyzer
    ) {}

    /**
     * Catat pengisian BBM penuh: hitung biaya (integer), jarak sejak isi sebelumnya, konsumsi km/l,
     * tandai anomali > 30% dari rata-rata, perbarui odometer truk, dan posting beban BBM (debit beban BBM, kredit kliring bank).
     */
    public function execute(
        User $user,
        Truck $truck,
        int $litersX1000,
        int $pricePerLiterIdr,
        int $odometerM,
        ?CarbonInterface $filledAt = null,
        ?Driver $driver = null
    ): FuelLog {
        if ($litersX1000 <= 0 || $pricePerLiterIdr <= 0) {
            throw FuelLogException::invalidAmount();
        }

        $this->authorize($user, $truck, $driver);

        return DB::transaction(function () use ($user, $truck, $litersX1000, $pricePerLiterIdr, $odometerM, $filledAt, $driver) {
            $truck = Truck::whereKey($truck->id)->lockForUpdate()->firstOrFail();
            $previous = FuelLog::where('truck_id', $truck->id)->orderByDesc('odometer_m')->first();

            if ($previous !== null) {
                if ($odometerM <= max((int) $truck->odometer_m, $previous->odometer_m)) {
                    throw FuelLogException::odometerNotIncreasing(max((int) $truck->odometer_m, $previous->odometer_m), $odometerM);
                }
            } elseif ($odometerM < (int) $truck->odometer_m) {
                throw FuelLogException::odometerNotIncreasing((int) $truck->odometer_m, $odometerM);
            }

            $distance = $previous ? $odometerM - $previous->odometer_m : null;
            $kmPerLiter = $distance !== null ? $this->analyzer->kmPerLiterX100($distance, $litersX1000) : null;

            $analysis = ['baseline' => null, 'deviation_bp' => null, 'is_anomaly' => false, 'note' => null];
            if ($kmPerLiter !== null) {
                $prior = FuelLog::where('truck_id', $truck->id)
                    ->whereNotNull('km_per_liter_x100')
                    ->where('is_anomaly', false)
                    ->orderByDesc('odometer_m')
                    ->limit(FuelConsumptionAnalyzer::BASELINE_WINDOW)
                    ->pluck('km_per_liter_x100')
                    ->map(fn ($v) => (int) $v)
                    ->all();
                $analysis = $this->analyzer->evaluate($kmPerLiter, $prior);
            }

            $cost = $this->analyzer->totalCostIdr($litersX1000, $pricePerLiterIdr);

            $log = FuelLog::create([
                'truck_id' => $truck->id,
                'driver_id' => $driver?->id,
                'schedule_id' => $this->activeScheduleId($truck, $driver),
                'liters_x1000' => $litersX1000,
                'price_per_liter_idr' => $pricePerLiterIdr,
                'total_cost_idr' => $cost,
                'odometer_m' => $odometerM,
                'previous_odometer_m' => $previous?->odometer_m,
                'distance_m' => $distance,
                'km_per_liter_x100' => $kmPerLiter,
                'baseline_km_per_liter_x100' => $analysis['baseline'],
                'deviation_bp' => $analysis['deviation_bp'],
                'is_anomaly' => $analysis['is_anomaly'],
                'anomaly_note' => $analysis['note'],
                'recorded_by' => $user->id,
                'filled_at' => $filledAt ?? now(),
            ]);

            $this->ledger->post(
                type: TransactionType::LOGISTICS_FUEL_EXPENSE,
                description: "BBM truk {$truck->plate_number}: ".number_format($litersX1000 / 1000, 3, ',', '.').' liter',
                idempotencyKey: "lgx:fuel:{$log->id}",
                entries: [
                    [LogisticsLedger::FUEL_EXPENSE, -$cost],
                    [LogisticsLedger::BANK_CLEARING, $cost],
                ],
                referenceType: FuelLog::class,
                referenceId: $log->id,
                createdBy: $user->id,
            );

            $truck->update(['odometer_m' => max((int) $truck->odometer_m, $odometerM)]);

            return $log;
        });
    }

    protected function authorize(User $user, Truck $truck, ?Driver $driver): void
    {
        if ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()) {
            return;
        }

        $own = $user->isDriver() ? Driver::where('user_id', $user->id)->first() : null;
        if (! $own || ($driver !== null && $driver->id !== $own->id) || $this->activeScheduleId($truck, $own) === null) {
            throw FuelLogException::forbidden();
        }
    }

    protected function activeScheduleId(Truck $truck, ?Driver $driver): ?int
    {
        if ($driver === null) {
            return null;
        }

        return Schedule::where('asset_type', Truck::class)
            ->where('asset_id', $truck->id)
            ->where('driver_id', $driver->id)
            ->whereIn('status', [ScheduleStatus::Scheduled->value, ScheduleStatus::Loading->value, ScheduleStatus::Departed->value])
            ->value('id');
    }
}
