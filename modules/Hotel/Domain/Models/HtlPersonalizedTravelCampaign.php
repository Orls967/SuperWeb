<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HtlPersonalizedTravelCampaign extends Model
{
    use HasUuids;

    protected $table = 'htl_personalized_travel_campaigns';

    protected $fillable = [
        'id',
        'travel_pass_id',
        'campaign_code',
        'offer_title',
        'destination_property_reference',
        'featured_package_title',
        'points_required_discounted',
        'valid_until',
        'affinity_score',
        'interaction_status',
    ];

    protected $casts = [
        'points_required_discounted' => 'integer',
        'valid_until' => 'datetime',
        'affinity_score' => 'float',
    ];

    public function travelPass(): BelongsTo
    {
        return $this->belongsTo(HtlTravelPass::class, 'travel_pass_id');
    }
}
