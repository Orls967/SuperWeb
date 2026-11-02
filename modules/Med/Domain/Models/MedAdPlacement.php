<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedAdPlacement extends Model
{
    use HasUuids;

    protected $table = 'med_ad_placements';

    protected $fillable = [
        'id',
        'channel_id',
        'placement_code',
        'location_name',
        'floor_cpm_minor',
        'is_active',
    ];

    protected $casts = [
        'floor_cpm_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MedDistributionChannel::class, 'channel_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MedAdCampaign::class, 'placement_id');
    }
}
