<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiseaseHealthScreening extends Model
{
    protected $table = 'mar_disease_health_screenings';

    protected $fillable = [
        'batch_id',
        'screening_date',
        'screening_agency',
        'sample_barcode',
        'wssv_pcr_negative',
        'imnv_pcr_negative',
        'ehp_pcr_negative',
        'ahpnd_pcr_negative',
        'vibrio_parahaemolyticus_controlled',
        'hepatopancreas_condition',
        'quarantine_status',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AquacultureBatch::class, 'batch_id');
    }
}
