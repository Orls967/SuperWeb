<?php

declare(strict_types=1);

namespace Modules\Fashion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FashionProductionAllocation extends Model
{
    protected $table = 'fsh_production_allocations';

    protected $fillable = [
        'production_order_id',
        'size_code',
        'color_code',
        'allocated_units',
    ];

    protected $casts = [
        'allocated_units' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(FashionProductionOrder::class, 'production_order_id');
    }
}
