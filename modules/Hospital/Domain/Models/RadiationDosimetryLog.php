<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Pemantauan Dosimetri Radiasi Pasien CT-Scan (Fase 105).
 *
 * @property int $id
 * @property int $study_id
 * @property int $patient_id
 * @property float $ctdi_vol_mgy
 * @property float $dlp_mgy_cm
 * @property float|null $effective_dose_msv
 * @property float|null $ssde_mgy
 * @property bool $drl_exceeded
 * @property string|null $safety_officer_review
 */
class RadiationDosimetryLog extends Model
{
    use HasFactory;

    protected $table = 'hsp_radiation_dosimetry_logs';

    protected $fillable = [
        'study_id',
        'patient_id',
        'ctdi_vol_mgy',
        'dlp_mgy_cm',
        'effective_dose_msv',
        'ssde_mgy',
        'drl_exceeded',
        'safety_officer_review',
    ];

    protected $casts = [
        'ctdi_vol_mgy' => 'float',
        'dlp_mgy_cm' => 'float',
        'effective_dose_msv' => 'float',
        'ssde_mgy' => 'float',
        'drl_exceeded' => 'boolean',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(ImagingStudy::class, 'study_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
