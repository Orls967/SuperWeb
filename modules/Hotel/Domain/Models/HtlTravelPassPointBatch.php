<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HtlTravelPassPointBatch extends Model
{
    use HasUuids;

    protected $table = 'htl_travel_pass_point_batches';

    protected $fillable = [
        'id',
        'travel_pass_id',
        'batch_number',
        'originating_line',
        'initial_pts',
        'remaining_pts',
        'liability_per_pt_idr',
        'total_liability_idr',
        'state',
        'reference_code',
        'earned_at',
        'expires_at',
        'breakage_recognized_at',
    ];

    protected $casts = [
        'initial_pts' => 'integer',
        'remaining_pts' => 'integer',
        'liability_per_pt_idr' => 'integer',
        'total_liability_idr' => 'integer',
        'earned_at' => 'datetime',
        'expires_at' => 'datetime',
        'breakage_recognized_at' => 'datetime',
    ];

    public function travelPass(): BelongsTo
    {
        return $this->belongsTo(HtlTravelPass::class, 'travel_pass_id');
    }
}
