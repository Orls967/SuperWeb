<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Exceptions\DriverLicenseExpiredException;
use Modules\Logistics\Domain\Exceptions\DrivingHoursLimitExceededException;
use Modules\Logistics\Domain\Exceptions\IncompatibleLicenseException;

class Driver extends LogisticsEntity
{
    use HasFactory;

    public const MAX_DAILY_DRIVING_MINUTES = 480; // 8 jam per hari (UU 22/2009 Pasal 90 ayat 2)

    public const MAX_CONTINUOUS_DRIVING_MINUTES = 240; // 4 jam berturut-turut (UU 22/2009 Pasal 90 ayat 3)

    public const MIN_REST_MINUTES = 30; // 30 menit istirahat (UU 22/2009 Pasal 90 ayat 3)

    protected $table = 'lgx_drivers';

    protected $fillable = [
        'user_id',
        'driver_number',
        'license_class',
        'license_expiry',
        'home_hub_id',
        'status',
        'daily_driving_minutes',
        'continuous_driving_minutes',
        'last_rest_at',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'daily_driving_minutes' => 'integer',
            'continuous_driving_minutes' => 'integer',
            'last_rest_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function homeHub(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'home_hub_id');
    }

    public function isLicenseValid(?CarbonInterface $referenceDate = null): bool
    {
        $date = $referenceDate ?? now();

        return $this->license_expiry->gte($date->startOfDay());
    }

    public function hasCompatibleLicense(string $requiredLicense): bool
    {
        $driverClass = trim($this->license_class);
        $req = trim($requiredLicense);

        if ($driverClass === $req) {
            return true;
        }

        // SIM B2 Umum mencakup seluruh kelas di bawahnya
        if ($driverClass === 'SIM B2 Umum') {
            return true;
        }

        // SIM B1 Umum mencakup SIM B1 dan SIM A, tetapi bukan B2 / B2 Umum
        if ($driverClass === 'SIM B1 Umum') {
            return in_array($req, ['SIM B1 Umum', 'SIM B1', 'SIM A'], true);
        }

        // SIM B2 (Polos/Pribadi) mencakup B1 dan A, tetapi bukan kelas Umum
        if ($driverClass === 'SIM B2') {
            return in_array($req, ['SIM B2', 'SIM B1', 'SIM A'], true);
        }

        if ($driverClass === 'SIM B1') {
            return in_array($req, ['SIM B1', 'SIM A'], true);
        }

        return false;
    }

    /**
     * Memvalidasi seluruh aturan penugasan trip pengemudi:
     * 1. Masa berlaku SIM (belum kedaluwarsa)
     * 2. Kesesuaian kelas SIM kendaraan
     * 3. Batas jam mengemudi harian maks 8 jam (UU 22/2009 Pasal 90 ayat 2)
     * 4. Batas mengemudi berturut-turut maks 4 jam sebelum istirahat 30 menit (UU 22/2009 Pasal 90 ayat 3)
     */
    public function validateAssignment(string $requiredLicense, int $tripMinutes, ?CarbonInterface $referenceDate = null): void
    {
        if (! $this->isLicenseValid($referenceDate)) {
            throw new DriverLicenseExpiredException(
                "SIM pengemudi {$this->user->name} ({$this->driver_number}) telah kedaluwarsa pada {$this->license_expiry->format('d/m/Y')}."
            );
        }

        if (! $this->hasCompatibleLicense($requiredLicense)) {
            throw new IncompatibleLicenseException(
                "Armada membutuhkan {$requiredLicense}, sedangkan pengemudi {$this->user->name} hanya memiliki {$this->license_class}."
            );
        }

        $projectedDaily = $this->daily_driving_minutes + $tripMinutes;
        if ($projectedDaily > self::MAX_DAILY_DRIVING_MINUTES) {
            throw new DrivingHoursLimitExceededException(
                "Penugasan trip ({$tripMinutes} menit) melebihi batas maksimal 8 jam mengemudi per hari sesuai UU 22/2009 Pasal 90 ayat (2) (total proyeksi: {$projectedDaily} menit)."
            );
        }

        $projectedContinuous = $this->continuous_driving_minutes + $tripMinutes;
        if ($projectedContinuous > self::MAX_CONTINUOUS_DRIVING_MINUTES) {
            throw new DrivingHoursLimitExceededException(
                "Pengemudi telah mengemudi {$this->continuous_driving_minutes} menit dan penugasan trip ini ({$tripMinutes} menit) melebihi batas 4 jam berturut-turut tanpa istirahat 30 menit sesuai UU 22/2009 Pasal 90 ayat (3)."
            );
        }
    }

    public function recordDrive(int $minutes): void
    {
        $this->daily_driving_minutes += $minutes;
        $this->continuous_driving_minutes += $minutes;
        $this->status = 'on_duty';
        $this->save();
    }

    public function recordRest(int $minutes): void
    {
        if ($minutes >= self::MIN_REST_MINUTES) {
            $this->continuous_driving_minutes = 0;
            $this->last_rest_at = now();
            $this->status = 'available';
            $this->save();
        }
    }

    public function resetDailyHours(): void
    {
        $this->daily_driving_minutes = 0;
        $this->continuous_driving_minutes = 0;
        $this->save();
    }
}
