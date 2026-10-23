<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RwaOffering extends Model
{
    protected $table = 'rwa_offerings';

    protected $fillable = [
        'rwa_asset_id',
        'offering_code',
        'tokens_offered',
        'tokens_sold',
        'price_per_token_idr',
        'min_lot_size',
        'lockup_days',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'tokens_offered' => 'integer',
        'tokens_sold' => 'integer',
        'price_per_token_idr' => 'integer',
        'min_lot_size' => 'integer',
        'lockup_days' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }
}
