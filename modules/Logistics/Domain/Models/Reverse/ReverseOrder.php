<?php

namespace Modules\Logistics\Domain\Models\Reverse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReverseOrder extends Model
{
    protected $table = 'lgx_reverse_orders';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(ReverseItem::class, 'reverse_order_id');
    }
}
