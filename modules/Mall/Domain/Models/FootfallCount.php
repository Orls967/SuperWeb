<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FootfallCount extends Model
{
    protected $table = 'mall_footfall_counts';

    protected $fillable = [
        'property_id',
        'date',
        'hour',
        'gate_name',
        'in_count',
        'out_count',
    ];

    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'in_count' => 'integer',
        'out_count' => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
