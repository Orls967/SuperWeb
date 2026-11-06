<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CarbonOrder extends Model
{
    use HasFactory;

    protected $table = 'egy_carbon_orders';

    protected $fillable = [
        'order_number',
        'party_id',
        'side',
        'vintage_year',
        'quantity_kg_co2e',
        'filled_kg_co2e',
        'limit_price_minor_per_ton',
        'status',
        'batch_id',
    ];

    protected $casts = [
        'party_id' => 'integer',
        'quantity_kg_co2e' => 'integer',
        'filled_kg_co2e' => 'integer',
        'limit_price_minor_per_ton' => 'integer',
        'batch_id' => 'integer',
    ];

    public function buyTrades(): HasMany
    {
        return $this->hasMany(CarbonTrade::class, 'buy_order_id');
    }

    public function sellTrades(): HasMany
    {
        return $this->hasMany(CarbonTrade::class, 'sell_order_id');
    }
}
