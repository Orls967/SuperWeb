<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AquacultureHatchery extends Model
{
    protected $table = 'mar_hatcheries';

    protected $fillable = [
        'hatchery_code',
        'name',
        'operator_name',
        'province',
        'spf_certificate_number',
        'broodstock_origin',
        'biosecurity_level',
        'certified_at',
        'expires_at',
        'status',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(AquacultureBatch::class, 'hatchery_id');
    }
}
