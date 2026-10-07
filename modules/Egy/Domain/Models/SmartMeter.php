<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SmartMeter extends Model
{
    protected $table = 'egy_smart_meters';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'total_kwh_accumulated' => 'float',
    ];
}
