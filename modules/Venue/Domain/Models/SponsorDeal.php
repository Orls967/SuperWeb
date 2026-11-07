<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SponsorDeal extends Model
{
    protected $table = 'ven_sponsor_deals';

    protected $fillable = [
        'event_id',
        'sponsor_name',
        'brand_category',
        'contract_value_idr',
        'target_impressions',
        'delivered_impressions',
        'cpm_rate_idr',
        'status',
    ];

    protected $casts = [
        'contract_value_idr' => 'integer',
        'target_impressions' => 'integer',
        'delivered_impressions' => 'integer',
        'cpm_rate_idr' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(SponsorMilestone::class, 'deal_id');
    }
}
