<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueBagInspection extends Model
{
    protected $table = 'ven_bag_inspections';

    protected $fillable = [
        'inspection_code',
        'event_id',
        'gate_code',
        'patron_ticket_id',
        'inspector_badge',
        'contraband_found',
        'contraband_type',
        'police_referred',
        'status',
        'inspected_at',
    ];

    protected $casts = [
        'contraband_found' => 'boolean',
        'police_referred' => 'boolean',
        'inspected_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTicket::class, 'patron_ticket_id');
    }
}
