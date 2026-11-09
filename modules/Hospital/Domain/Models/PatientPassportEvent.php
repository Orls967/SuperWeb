<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientPassportEvent extends Model
{
    protected $table = 'hsp_patient_passport_events';

    protected $fillable = [
        'patient_id',
        'sequence_no',
        'event_type',
        'encrypted_payload',
        'payload_hash',
        'prev_hash',
        'current_hash',
        'signature',
        'actor_id',
        'recorded_at',
    ];

    protected $casts = [
        'sequence_no' => 'integer',
        'encrypted_payload' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
