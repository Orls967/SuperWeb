<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelPassPointsEntry extends Model
{
    protected $table = 'htl_travel_pass_points_ledger';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'travel_pass_id',
        'transaction_type',
        'originating_line',
        'pts_amount',
        'liability_value_idr',
        'reference_code',
    ];

    public function pass(): BelongsTo
    {
        return $this->belongsTo(TravelPass::class, 'travel_pass_id');
    }
}
