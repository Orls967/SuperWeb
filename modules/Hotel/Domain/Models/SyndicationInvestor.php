<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyndicationInvestor extends Model
{
    protected $table = 'htl_syndication_investors';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'syndication_code',
        'property_id',
        'investor_user_id',
        'fractional_tokens_held',
        'ownership_percentage',
        'distributed_yield_idr',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }
}
