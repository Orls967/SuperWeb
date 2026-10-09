<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PpaContract extends Model
{
    protected $table = 'egy_ppa_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'feed_in_tariff_per_kwh_minor' => 'float',
        'surplus_kwh_exported' => 'float',
        'total_settlement_minor' => 'integer',
    ];
}
