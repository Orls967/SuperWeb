<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SybCommodityLot extends Model
{
    protected $table = 'syb_commodity_lots';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'lot_number',
        'exchange_name',
        'commodity_type',
        'physical_serial_number',
        'warehouse_location',
        'unit_quantity',
        'acquisition_cost_cents',
        'lot_status',
        'assigned_order_ref',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'unit_quantity' => 'integer',
        'acquisition_cost_cents' => 'integer',
    ];

    /**
     * @return HasOne<SybTawarruqOrder, $this>
     */
    public function tawarruqOrder(): HasOne
    {
        return $this->hasOne(SybTawarruqOrder::class, 'commodity_lot_id');
    }
}
