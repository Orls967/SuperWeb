<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseContract extends Model
{
    protected $table = 'htl_franchise_contracts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'contract_code',
        'property_id',
        'contract_type',
        'initial_franchise_fee_idr',
        'royalty_percentage',
        'status',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }
}
