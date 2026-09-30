<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\UtilityType;

class UtilityTariff extends Model
{
    protected $table = 'mall_utility_tariffs';

    protected $fillable = [
        'property_id',
        'utility_type',
        'tier_number',
        'tier_min',
        'tier_max',
        'rate_per_unit',
        'standing_charge',
        'effective_from',
    ];

    protected $casts = [
        'utility_type' => UtilityType::class,
        'tier_number' => 'integer',
        'tier_min' => 'float',
        'tier_max' => 'float',
        'rate_per_unit' => 'integer',
        'standing_charge' => 'integer',
        'effective_from' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
