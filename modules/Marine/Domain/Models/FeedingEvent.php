<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedingEvent extends Model
{
    protected $table = 'mar_feeding_events';

    protected $fillable = [
        'batch_id',
        'feed_inventory_batch_id',
        'feeding_date',
        'feeding_tray_number',
        'quantity_dispensed_kg',
        'estimated_feed_consumption_pct',
        'daily_sampling_abw_grams',
        'daily_fcr_estimate',
        'feeding_technique',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AquacultureBatch::class, 'batch_id');
    }

    public function feedInventory(): BelongsTo
    {
        return $this->belongsTo(FeedInventoryBatch::class, 'feed_inventory_batch_id');
    }
}
