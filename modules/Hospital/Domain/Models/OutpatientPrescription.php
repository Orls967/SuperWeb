<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutpatientPrescription extends Model
{
    protected $table = 'hsp_outpatient_prescriptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_idr' => 'integer',
            'subtotal_idr' => 'integer',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(OutpatientEncounter::class, 'encounter_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function dispensings(): HasMany
    {
        return $this->hasMany(OutpatientDispensing::class, 'prescription_id');
    }
}
