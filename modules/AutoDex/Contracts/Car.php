<?php

declare(strict_types=1);

namespace Modules\AutoDex\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cross-module contract for Car model.
 */
class Car extends Model
{
    protected $table = 'dex_cars';

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
