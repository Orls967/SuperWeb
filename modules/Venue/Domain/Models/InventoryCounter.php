<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCounter extends Model
{
    protected $table = 'ven_inventory_counters';

    protected $fillable = [
        'event_id',
        'tier_id',
        'total_quota',
        'held_count',
        'sold_count',
    ];

    protected $casts = [
        'total_quota' => 'integer',
        'held_count' => 'integer',
        'sold_count' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(TicketTier::class, 'tier_id');
    }
}
