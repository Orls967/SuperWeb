<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestivalBundleOrder extends Model
{
    protected $table = 'ven_festival_bundle_orders';

    protected $fillable = [
        'event_id',
        'customer_id',
        'order_number',
        'bundle_code',
        'bundle_price_idr',
        'status',
    ];

    protected $casts = [
        'bundle_price_idr' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FestivalBundleAllocation::class, 'order_id');
    }
}
