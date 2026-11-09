<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeBlueEvent extends Model
{
    protected $table = 'hsp_code_blue_events';

    protected $fillable = [
        'event_no',
        'admission_id',
        'encounter_id',
        'bed_id',
        'status',
        'escalation_level',
        'nurse_ack_at',
        'doctor_ack_at',
        'team_ack_at',
        'resolved_at',
        'proof_hash',
    ];

    protected $casts = [
        'nurse_ack_at' => 'datetime',
        'doctor_ack_at' => 'datetime',
        'team_ack_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(HospitalBed::class, 'bed_id');
    }
}
