<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarInventoryItem extends Model
{
    protected $table = 'ven_bar_inventory_items';

    protected $fillable = [
        'venue_id',
        'sku',
        'brand_name',
        'category',
        'alcohol_tier',
        'unit_volume_ml',
        'current_stock_bottles',
        'safety_stock_bottles',
        'cost_per_bottle_idr',
        'selling_price_per_bottle_idr',
        'cif_per_bottle_idr',
        'excise_duty_per_bottle_idr',
    ];

    protected $casts = [
        'unit_volume_ml' => 'integer',
        'current_stock_bottles' => 'integer',
        'safety_stock_bottles' => 'integer',
        'cost_per_bottle_idr' => 'integer',
        'selling_price_per_bottle_idr' => 'integer',
        'cif_per_bottle_idr' => 'integer',
        'excise_duty_per_bottle_idr' => 'integer',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id');
    }
}
