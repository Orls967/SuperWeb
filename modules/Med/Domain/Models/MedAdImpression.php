<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class MedAdImpression extends Model
{
    use HasUuids;

    protected $table = 'med_ad_impressions';

    protected $fillable = [
        'id',
        'campaign_id',
        'impression_hash',
        'device_ip_hash',
        'timestamp_utc',
        'is_verified',
        'is_fraud',
        'fraud_reason',
    ];

    protected $casts = [
        'timestamp_utc' => 'datetime',
        'is_verified' => 'boolean',
        'is_fraud' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new RuntimeException('MedAdImpression records are strictly immutable and append-only.');
        });

        static::deleting(function () {
            throw new RuntimeException('MedAdImpression records cannot be deleted.');
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MedAdCampaign::class, 'campaign_id');
    }
}
