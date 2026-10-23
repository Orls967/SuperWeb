<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    protected $table = 'hsp_prescriptions';

    protected $fillable = [
        'rx_code',
        'encounter_id',
        'drug_code',
        'drug_name',
        'qty_prescribed',
        'dosage_instructions',
        'contraindication_alert',
        'status',
    ];

    protected $casts = [
        'contraindication_alert' => 'boolean',
    ];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }
}
