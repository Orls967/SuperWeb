<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnologyLicense extends Model
{
    protected $table = 'intl_technology_licenses';

    protected $guarded = [];

    protected $casts = [
        'is_exclusive' => 'boolean',
        'royalty_rate_percent' => 'float',
        'minimum_annual_guarantee_idr' => 'integer',
        'start_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function foreignEntity(): BelongsTo
    {
        return $this->belongsTo(ForeignEntity::class, 'foreign_entity_id');
    }
}
