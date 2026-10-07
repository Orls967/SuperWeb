<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateParityViolation extends Model
{
    protected $table = 'htl_rate_parity_violations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'violation_code',
        'property_id',
        'ota_channel_name',
        'direct_bar_rate_idr',
        'ota_undercut_rate_idr',
        'penalty_levy_idr',
        'status',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }
}
