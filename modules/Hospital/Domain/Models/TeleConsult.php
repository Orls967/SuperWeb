<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeleConsult extends Model
{
    protected $table = 'hsp_tele_consults';

    protected $fillable = [
        'consult_code',
        'patient_id',
        'doctor_id',
        'channel_type',
        'triage_category',
        'chief_complaint',
        'clinical_notes',
        'status',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function epharmacyOrders(): HasMany
    {
        return $this->hasMany(EPharmacyOrder::class, 'tele_consult_id');
    }
}
