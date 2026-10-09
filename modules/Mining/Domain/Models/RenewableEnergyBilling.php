<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RenewableEnergyBilling extends Model
{
    protected $table = 'min_renewable_energy_billings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'energy_kwh_delivered' => 'float',
        'rate_per_kwh_minor' => 'integer',
        'total_amount_minor' => 'integer',
        'scope1_avoided_tons_co2' => 'float',
    ];
}
