<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningWorkPermit extends Model
{
    protected $table = 'min_work_permits';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'permit_number',
        'permit_type',
        'supervisor_name',
        'worker_party_id',
        'allowed_pit_id',
        'allowed_latitude',
        'allowed_longitude',
        'allowed_radius_meters',
        'valid_from',
        'valid_until',
        'status',
    ];

    protected $casts = [
        'allowed_latitude' => 'float',
        'allowed_longitude' => 'float',
        'allowed_radius_meters' => 'float',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];
}
