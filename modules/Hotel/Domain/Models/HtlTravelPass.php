<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Hotel\Domain\Algorithms\GlobalTierQualifier;

class HtlTravelPass extends Model
{
    use HasUuids;

    protected $table = 'htl_travel_passes';

    protected $fillable = [
        'id',
        'pass_number',
        'user_id',
        'user_reference',
        'tier',
        'cumulative_nights',
        'cumulative_spend_idr',
        'pts_balance',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'cumulative_nights' => 'integer',
        'cumulative_spend_idr' => 'integer',
        'pts_balance' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->user_id === null) {
                $model->user_id = 1;
            }
        });
    }

    public function pointBatches(): HasMany
    {
        return $this->hasMany(HtlTravelPassPointBatch::class, 'travel_pass_id');
    }

    public function transferLogs(): HasMany
    {
        return $this->hasMany(HtlAllianceTransferLog::class, 'travel_pass_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(HtlPersonalizedTravelCampaign::class, 'travel_pass_id');
    }

    /**
     * Evaluasi dan perbarui tier keanggotaan berdasarkan algoritma H13
     */
    public function refreshTier(): void
    {
        $evaluator = new GlobalTierQualifier;
        $cert = $evaluator->evaluate(
            rolling365Nights: $this->cumulative_nights,
            rolling365SpendIdr: $this->cumulative_spend_idr,
            currentTier: $this->tier
        );

        if ($cert->qualifiedTier !== $this->tier) {
            $this->update(['tier' => $cert->qualifiedTier]);
        }
    }
}
