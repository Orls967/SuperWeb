<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RenewableEnergyCertificate extends Model
{
    protected $table = 'egy_renewable_energy_certificates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'certificate_serial',
        'generation_asset_id',
        'owner_entity_id',
        'energy_mwh',
        'is_retired',
        'retired_by_property_id',
        'retired_at',
    ];

    protected $casts = [
        'energy_mwh' => 'float',
        'is_retired' => 'boolean',
        'retired_at' => 'datetime',
    ];
}
