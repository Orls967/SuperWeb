<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Package extends LogisticsEntity
{
    protected $table = 'lgx_packages';

    protected $fillable = [
        'shipment_id',
        'weight_g',
        'length_mm',
        'width_mm',
        'height_mm',
        'description',
        'hs_code',
        'dg_un_number',
        'dg_class',
        'temp_min_c10',
        'temp_max_c10',
    ];

    protected $casts = [
        'weight_g' => 'integer',
        'length_mm' => 'integer',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
        'temp_min_c10' => 'integer',
        'temp_max_c10' => 'integer',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function isDangerousGoods(): bool
    {
        return ! empty($this->dg_un_number) || ! empty($this->dg_class);
    }

    public function isReefer(): bool
    {
        return $this->temp_min_c10 !== null || $this->temp_max_c10 !== null;
    }
}
