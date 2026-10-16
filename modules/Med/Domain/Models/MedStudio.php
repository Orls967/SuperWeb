<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedStudio extends Model
{
    protected $table = 'med_studios';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'studio_code',
        'name',
        'facility_type',
        'location_city',
        'hourly_rate_minor',
        'full_day_rate_minor',
        'status',
    ];

    protected $casts = [
        'hourly_rate_minor' => 'integer',
        'full_day_rate_minor' => 'integer',
    ];
}
