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
        'valid_until',
        'status',
    ];

    protected $casts = [
        'valid_until' => 'datetime',
    ];
}
