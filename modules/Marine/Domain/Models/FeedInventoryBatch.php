<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedInventoryBatch extends Model
{
    protected $table = 'mar_feed_inventory_batches';

    protected $fillable = [
        'feed_lot_number',
        'manufacturer_name',
        'feed_product_name',
        'protein_percentage',
        'lipid_percentage',
        'marine_raw_materials_sustainable',
        'feed_certification',
        'quantity_kg',
        'remaining_kg',
        'unit_price_minor_cents',
        'manufactured_at',
        'expires_at',
    ];

    public function feedingEvents(): HasMany
    {
        return $this->hasMany(FeedingEvent::class, 'feed_inventory_batch_id');
    }
}
