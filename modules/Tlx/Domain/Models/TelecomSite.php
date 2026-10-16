<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelecomSite extends Model
{
    protected $table = 'tlx_sites';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'site_code',
        'site_name',
        'site_type',
        'region',
        'latitude',
        'longitude',
        'status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
