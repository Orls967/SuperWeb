<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Model Studi Pencitraan Radiologi DICOM PACS (Fase 105).
 *
 * @property int $id
 * @property string $study_instance_uid
 * @property int $patient_id
 * @property string $modality
 * @property string $body_part
 * @property Carbon $performed_at
 * @property int $turnaround_minutes
 * @property string|null $radiologist_findings
 * @property int $fee_idr
 * @property string $status
 */
class ImagingDicomStudy extends ImagingStudy
{
    use HasFactory;

    protected $table = 'hsp_imaging_studies';

    protected $fillable = [
        'study_instance_uid',
        'patient_id',
        'modality',
        'body_part',
        'performed_at',
        'turnaround_minutes',
        'radiologist_findings',
        'fee_idr',
        'status',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'turnaround_minutes' => 'integer',
        'fee_idr' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function series(): HasMany
    {
        return $this->hasMany(ImagingSeries::class, 'study_id');
    }

    public function dosimetryLogs(): HasMany
    {
        return $this->hasMany(RadiationDosimetryLog::class, 'study_id');
    }
}
