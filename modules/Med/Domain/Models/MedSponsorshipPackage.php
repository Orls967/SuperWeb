<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedSponsorshipPackage extends Model
{
    protected $table = 'med_sponsorship_packages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'package_code',
        'sponsor_brand_id',
        'event_or_entity_ref',
        'package_tier',
        'total_sponsorship_minor',
        'bundled_channels',
        'status',
    ];

    protected $casts = [
        'total_sponsorship_minor' => 'integer',
        'bundled_channels' => 'array',
    ];
}
