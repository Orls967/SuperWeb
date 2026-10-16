<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalOrder extends Model
{
    protected $table = 'hsp_orders';

    protected $fillable = [
        'order_code',
        'encounter_id',
        'order_type',
        'description',
        'sequence_step',
        'scheduled_at',
        'status',
        'delay_alert_sent',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'delay_alert_sent' => 'boolean',
    ];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }
}
