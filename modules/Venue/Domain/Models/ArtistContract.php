<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ArtistContract extends Model
{
    protected $table = 'ven_artist_contracts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'venue_id',
        'event_id',
        'contract_number',
        'artist_name',
        'advance_amount',
        'door_share_percentage',
        'total_door_sales',
        'door_share_gross',
        'net_payout_amount',
        'currency',
        'status',
    ];

    protected $casts = [
        'advance_amount' => 'integer',
        'door_share_percentage' => 'decimal:2',
        'total_door_sales' => 'integer',
        'door_share_gross' => 'integer',
        'net_payout_amount' => 'integer',
    ];
}
