<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicBundlePackage extends Model
{
    protected $table = 'ven_dynamic_bundle_packages';

    protected $fillable = [
        'package_code',
        'event_id',
        'package_name',
        'base_price_idr',
        'floor_cost_idr',
        'final_price_idr',
        'surge_bps',
        'lead_time_discount_bps',
        'status',
    ];

    protected $casts = [
        'base_price_idr' => 'integer',
        'floor_cost_idr' => 'integer',
        'final_price_idr' => 'integer',
        'surge_bps' => 'integer',
        'lead_time_discount_bps' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(DynamicBundleComponent::class, 'bundle_package_id');
    }
}
