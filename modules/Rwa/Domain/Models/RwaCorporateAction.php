<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RwaCorporateAction extends Model
{
    protected $table = 'rwa_corporate_actions';

    protected $fillable = [
        'rwa_asset_id',
        'action_type',
        'tokens_affected',
        'redemption_payout_per_token_idr',
        'prev_hash',
        'block_hash',
        'executed_at',
    ];

    protected $casts = [
        'tokens_affected' => 'integer',
        'redemption_payout_per_token_idr' => 'integer',
        'executed_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }
}
