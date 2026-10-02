<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Actions\VerifyPassportAction;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\DriverNotAssignableException;
use Modules\Logistics\Domain\Exceptions\DrivingHoursLimitExceededException;
use Modules\Logistics\Domain\Exceptions\InvalidDispatchException;
use Modules\Logistics\Domain\Exceptions\InvalidVehiclePassportException;
use Modules\Logistics\Domain\Exceptions\ScheduleConflictException;
use Modules\Logistics\Domain\Exceptions\TruckNotAssignableException;
use Modules\Logistics\Domain\Models\DispatchAssignment;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Truck;

class AssignScheduleResourcesAction
{
    public function __construct(
        protected VerifyPassportAction $verifyPassport,
        protected ReleaseScheduleResourcesAction $release
    ) {}

    /**
     * Tugaskan truk + pengemudi ke jadwal trip darat, dengan seluruh validasi dispatch:
     * status & maintenance truk, paspor kendaraan, SIM (masa berlaku + kelas), jam mengemudi
     * (UU 22/2009 Pasal 90) dan tabrakan jadwal.
     */
    public function execute(Schedule $schedule, Truck $truck, Driver $driver, User $dispatcher): DispatchAssignment
    {
        return DB::transaction(function () use ($schedule, $truck, $driver, $dispatcher) {
            $schedule = Schedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            $truck = Truck::whereKey($truck->id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if ($schedule->mode !== TransportMode::ROAD) {
                throw new InvalidDispatchException("Jadwal {$schedule->schedule_number} bukan trip darat; hanya trip darat yang dapat ditugaskan truk dan pengemudi.");
            }

            if (! in_array($schedule->status, [ScheduleStatus::Scheduled, ScheduleStatus::Loading], true)) {
                throw new InvalidDispatchException("Jadwal {$schedule->schedule_number} berstatus '{$schedule->status->value}' dan tidak dapat diubah penugasannya.");
            }

            $this->validateTruck($schedule, $truck);
            $this->validateDriver($schedule, $driver, $truck);

            // Lepas penugasan sebelumnya (reassign) agar truk lama kembali tersedia.
            if ($schedule->asset_id || $schedule->driver_id) {
                $this->release->execute($schedule, 'Digantikan penugasan baru');
                $schedule->refresh();
            }

            $tripMinutes = $this->tripMinutes($schedule);

            $schedule->asset_type = Truck::class;
            $schedule->asset_id = $truck->id;
            $schedule->driver_id = $driver->id;
            $schedule->save();

            $truck->status = FleetStatus::ASSIGNED;
            $truck->save();

            return DispatchAssignment::create([
                'schedule_id' => $schedule->id,
                'truck_id' => $truck->id,
                'driver_id' => $driver->id,
                'assigned_by' => $dispatcher->id,
                'trip_minutes' => $tripMinutes,
                'status' => DispatchAssignment::STATUS_ACTIVE,
                'assigned_at' => now(),
            ]);
        });
    }

    protected function tripMinutes(Schedule $schedule): int
    {
        return max(1, (int) $schedule->etd->diffInMinutes($schedule->eta, true));
    }

    protected function validateTruck(Schedule $schedule, Truck $truck): void
    {
        $alreadyOnThisSchedule = $schedule->asset_type === Truck::class && (int) $schedule->asset_id === $truck->id;

        if ($truck->status === FleetStatus::MAINTENANCE) {
            throw TruckNotAssignableException::forTruck($truck->plate_number, 'armada sedang dalam perawatan (maintenance).');
        }

        if ($truck->status === FleetStatus::RETIRED) {
            throw TruckNotAssignableException::forTruck($truck->plate_number, 'armada sudah pensiun.');
        }

        if (! $alreadyOnThisSchedule && $truck->status !== FleetStatus::AVAILABLE) {
            throw TruckNotAssignableException::forTruck($truck->plate_number, "status armada '{$truck->status->value}', bukan tersedia.");
        }

        // Paspor kendaraan: rantai hash harus utuh sebelum armada dilepas ke jalan.
        if ($truck->vehicle) {
            $passport = $this->verifyPassport->execute($truck->vehicle);
            if (! $passport['is_valid']) {
                throw InvalidVehiclePassportException::forPlate($truck->plate_number, $passport['message']);
            }
        }

        $conflict = $this->overlapping($schedule)
            ->where('asset_type', Truck::class)
            ->where('asset_id', $truck->id)
            ->first();

        if ($conflict) {
            throw ScheduleConflictException::forAsset("Truck {$truck->plate_number}", $conflict->schedule_number);
        }
    }

    protected function validateDriver(Schedule $schedule, Driver $driver, Truck $truck): void
    {
        if ($driver->status === 'suspended') {
            throw DriverNotAssignableException::forDriver($driver->driver_number, 'pengemudi sedang dinonaktifkan (suspended).');
        }

        $conflict = $this->overlapping($schedule)->where('driver_id', $driver->id)->first();
        if ($conflict) {
            throw ScheduleConflictException::forDriver($driver->driver_number, $conflict->schedule_number);
        }

        $tripMinutes = $this->tripMinutes($schedule);

        // SIM (masa berlaku, kelas) + batas harian & berturut-turut.
        $driver->validateAssignment($truck->required_license, $tripMinutes, $schedule->etd->copy());

        // Beban harian juga mencakup trip lain yang sudah ditugaskan pada hari yang sama.
        $pending = (int) DispatchAssignment::where('driver_id', $driver->id)
            ->where('status', DispatchAssignment::STATUS_ACTIVE)
            ->where('schedule_id', '!=', $schedule->id)
            ->whereHas('schedule', fn ($q) => $q
                ->whereDate('etd', $schedule->etd->toDateString())
                ->whereIn('status', [ScheduleStatus::Scheduled->value, ScheduleStatus::Loading->value]))
            ->sum('trip_minutes');

        if ($driver->daily_driving_minutes + $pending + $tripMinutes > Driver::MAX_DAILY_DRIVING_MINUTES) {
            throw new DrivingHoursLimitExceededException(
                "Pengemudi {$driver->driver_number} sudah memiliki {$pending} menit trip lain pada hari yang sama; menambah {$tripMinutes} menit melampaui batas 8 jam per hari (UU 22/2009 Pasal 90 ayat 2)."
            );
        }
    }

    /**
     * @return Builder<Schedule>
     */
    protected function overlapping(Schedule $schedule)
    {
        return Schedule::where('id', '!=', $schedule->id)
            ->whereNotIn('status', [ScheduleStatus::Completed->value, ScheduleStatus::Cancelled->value])
            ->where('etd', '<', $schedule->eta)
            ->where('eta', '>', $schedule->etd);
    }
}
