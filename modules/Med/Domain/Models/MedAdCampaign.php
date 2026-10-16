<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedAdCampaign extends Model
{
    protected $table = 'med_ad_campaigns';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'campaign_code',
        'advertiser_entity_id',
        'agency_entity_id',
        'agency_commission_pct',
        'channel_id',
        'target_impressions',
        'verified_impressions',
        'floor_cpm_minor',
        'actual_cpm_minor',
        'total_spend_minor',
        'agency_commission_minor',
        'publisher_share_minor',
        'platform_share_minor',
        'status',
    ];

    protected $casts = [
        'agency_commission_pct' => 'float',
        'target_impressions' => 'integer',
        'verified_impressions' => 'integer',
        'floor_cpm_minor' => 'integer',
        'actual_cpm_minor' => 'integer',
        'total_spend_minor' => 'integer',
        'agency_commission_minor' => 'integer',
        'publisher_share_minor' => 'integer',
        'platform_share_minor' => 'integer',
    ];
}
