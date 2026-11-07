<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicBundleComponent extends Model
{
    protected $table = 'ven_dynamic_bundle_components';

    protected $fillable = [
        'bundle_package_id',
        'component_type',
        'name',
        'base_price_idr',
        'floor_cost_idr',
        'metadata',
    ];

    protected $casts = [
        'base_price_idr' => 'integer',
        'floor_cost_idr' => 'integer',
        'metadata' => 'array',
    ];

    public function bundlePackage(): BelongsTo
    {
        return $this->belongsTo(DynamicBundlePackage::class, 'bundle_package_id');
    }
}
