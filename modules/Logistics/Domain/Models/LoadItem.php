<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadItem extends LogisticsEntity
{
    protected $table = 'lgx_load_items';

    protected $fillable = [
        'load_id',
        'shipment_id',
        'package_id',
        'sequence',
        'weight_kg',
        'volume_dm3',
        'dg_class',
        'is_reefer',
        'loaded_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'weight_kg' => 'decimal:3',
        'volume_dm3' => 'integer',
        'is_reefer' => 'boolean',
        'loaded_at' => 'datetime',
    ];

    public function cargoLoad(): BelongsTo
    {
        return $this->belongsTo(Load::class, 'load_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
