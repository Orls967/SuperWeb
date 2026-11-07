<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalBundleAllocation extends Model
{
    protected $table = 'ven_festival_bundle_allocations';

    protected $fillable = [
        'order_id',
        'component_name',
        'vendor_code',
        'ssp_idr',
        'allocated_amount_idr',
        'platform_fee_idr',
        'vendor_net_idr',
        'status',
    ];

    protected $casts = [
        'ssp_idr' => 'integer',
        'allocated_amount_idr' => 'integer',
        'platform_fee_idr' => 'integer',
        'vendor_net_idr' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FestivalBundleOrder::class, 'order_id');
    }
}
