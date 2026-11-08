<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationTicketTransition extends Model
{
    public $timestamps = false;
    protected $table = 'avi_ticket_transitions';

    protected $fillable = [
        'ticket_id',
        'from_status',
        'to_status',
        'trigger',
        'actor_id',
        'reason',
        'created_at',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(AviationTicket::class, 'ticket_id');
    }
}

