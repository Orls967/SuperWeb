<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnologyLicense extends Model
{
    protected $table = 'intl_technology_licenses';

    protected $fillable = [
        'license_code',
        'title',
        'foreign_entity_id',
        'license_type',
        'territory',
        'is_exclusive',
        'royalty_rate_percent',
        'minimum_annual_guarantee_idr',
        'start_date',
        'expiry_date',
        'status',
    ];

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
