<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetInsurance extends Model
{
    protected $table = 'ast_insurances';

    protected $fillable = [
        'asset_id', 'policy_number', 'provider', 'coverage_amount_idr',
        'annual_premium_idr', 'start_date', 'end_date', 'status',
        'claim_amount_idr', 'claim_notes',
    ];

    protected $casts = [
        'coverage_amount_idr' => 'integer',
        'annual_premium_idr' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'claim_amount_idr' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
