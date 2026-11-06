<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BiogasWteProduction extends Model
{
    protected $table = 'egy_biogas_wte_productions';

    protected $fillable = [
        'facility_id',
        'production_date',
        'biogas_volume_m3',
        'electricity_generated_kwh',
        'methane_content_bps',
        'run_hours',
    ];

    protected $casts = [
        'production_date' => 'date',
        'biogas_volume_m3' => 'integer',
        'electricity_generated_kwh' => 'integer',
        'methane_content_bps' => 'integer',
        'run_hours' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(WasteTreatmentFacility::class, 'facility_id');
    }
}
