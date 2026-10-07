<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RightsContract extends Model
{
    protected $table = 'ven_rights_contracts';

    protected $fillable = [
        'contract_code',
        'creator_id',
        'royalty_share_percent',
        'payout_hold_window_days',
        'accrued_royalties_idr',
        'held_royalties_idr',
        'paid_royalties_idr',
        'status',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }
}
