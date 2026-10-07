<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WeighbridgeTicket extends Model
{
    protected $table = 'min_weighbridge_tickets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'ticket_number',
        'truck_plate_number',
        'gross_weight_ton',
        'tare_weight_ton',
        'net_weight_ton',
        'nickel_grade_percentage',
        'destination_stockpile',
        'hash',
        'previous_hash',
    ];

    protected $casts = [
        'gross_weight_ton' => 'decimal:2',
        'tare_weight_ton' => 'decimal:2',
        'net_weight_ton' => 'decimal:2',
        'nickel_grade_percentage' => 'decimal:2',
    ];
}
