<?php

namespace Modules\TradeFinance\Domain\Models\CrossBorder;

use Illuminate\Database\Eloquent\Model;

class CbamCertificate extends Model
{
    protected $table = 'tf_cbam_certificates';

    protected $guarded = [];

    protected $casts = [
        'net_mass_tons' => 'decimal:2',
        'embedded_emissions_tco2' => 'decimal:2',
    ];
}
