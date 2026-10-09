<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RenewableEnergyCertificate extends Model
{
    protected $table = 'egy_renewable_energy_certificates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'energy_mwh' => 'float',
        'is_retired' => 'boolean',
        'retired_at' => 'datetime',
    ];
}
