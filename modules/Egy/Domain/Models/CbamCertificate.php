<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CbamCertificate extends Model
{
    protected $table = 'egy_cbam_certificates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'embedded_emissions_tons_co2' => 'float',
        'cbam_price_per_ton_minor' => 'integer',
        'total_cbam_fee_minor' => 'integer',
    ];
}
