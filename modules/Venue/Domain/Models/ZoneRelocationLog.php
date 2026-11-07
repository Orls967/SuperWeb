<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneRelocationLog extends Model
{
    protected $table = 'ven_zone_relocation_logs';

    protected $fillable = [
        'relocation_code',
        'event_id',
        'from_zone_id',
        'to_zone_id',
        'ticket_id',
        'reason',
        'refund_amount_idr',
        'status',
    ];

    protected $casts = [
        'refund_amount_idr' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function fromZone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'from_zone_id');
    }

    public function toZone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'to_zone_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTicket::class, 'ticket_id');
    }
}
