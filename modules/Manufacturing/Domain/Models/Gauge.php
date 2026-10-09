<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Alat ukur: jadwal kalibrasi; alat kedaluwarsa memblokir inspeksi. */
class Gauge extends Model
{
    protected $table = 'mfg_gauges';

    protected $fillable = ['code', 'name', 'calibrated_at', 'calibration_due', 'certificate_ref', 'is_active'];

    protected $casts = ['calibrated_at' => 'datetime', 'calibration_due' => 'datetime', 'is_active' => 'boolean'];

    public function isCalibrationValid(): bool
    {
        // is_active null (belum di-set di instance) = default DB true;
        // hanya false eksplisit yang menonaktifkan alat.
        return $this->is_active !== false
            && $this->calibration_due !== null
            && $this->calibration_due->isFuture();
    }
}
