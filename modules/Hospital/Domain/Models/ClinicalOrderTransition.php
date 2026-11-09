<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalOrderTransition extends Model
{
    protected $table = 'hsp_clinical_order_transitions';

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'delay_minutes',
        'note',
        'actor_id',
        'transitioned_at',
    ];

    protected $casts = [
        'delay_minutes' => 'integer',
        'transitioned_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(HospitalOrder::class, 'order_id');
    }
}
