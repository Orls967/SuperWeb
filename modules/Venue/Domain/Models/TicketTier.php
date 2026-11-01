<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TicketTier extends Model
{
    protected $table = 'ven_ticket_tiers';

    protected $fillable = [
        'event_id',
        'zone_id',
        'name',
        'tier_code',
        'tier_type',
        'price_idr',
        'quota',
        'is_numbered_seating',
        'allows_reentry',
    ];

    protected $casts = [
        'price_idr' => 'integer',
        'quota' => 'integer',
        'is_numbered_seating' => 'boolean',
        'allows_reentry' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }

    public function counter(): HasOne
    {
        return $this->hasOne(InventoryCounter::class, 'tier_id');
    }

    public function seatClaims(): HasMany
    {
        return $this->hasMany(SeatClaim::class, 'tier_id');
    }
}
