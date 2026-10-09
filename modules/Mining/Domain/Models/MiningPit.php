<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningPit extends Model
{
    protected $table = 'min_pits';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'pit_code',
        'name',
        'target_production_ton',
        'actual_production_ton',
    ];

    protected $casts = [
        'target_production_ton' => 'integer',
        'actual_production_ton' => 'integer',
    ];
}
