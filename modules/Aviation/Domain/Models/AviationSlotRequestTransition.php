<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationSlotRequestTransition extends Model
{
    public $timestamps = false;
    protected $table = 'avi_slot_request_transitions';

    protected $fillable = [
        'slot_request_id',
        'from_status',
        'to_status',
        'trigger',
        'actor_id',
        'reason',
        'created_at',
    ];

    public function slotRequest(): BelongsTo
    {
        return $this->belongsTo(AviationSlotRequest::class, 'slot_request_id');
    }
}

