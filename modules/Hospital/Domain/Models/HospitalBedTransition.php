<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalBedTransition extends Model
{
    protected $table = 'hsp_bed_transitions';

    protected $fillable = [
        'bed_id',
        'from_status',
        'to_status',
        'reason',
        'actor_id',
        'transitioned_at',
    ];

    protected $casts = [
        'transitioned_at' => 'datetime',
    ];

    public function bed(): BelongsTo
    {
        return $this->belongsTo(HospitalBed::class, 'bed_id');
    }
}
