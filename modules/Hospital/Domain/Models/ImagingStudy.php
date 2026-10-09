<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagingStudy extends Model
{
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

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
