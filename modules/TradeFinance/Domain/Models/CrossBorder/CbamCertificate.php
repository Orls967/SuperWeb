<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models\CrossBorder;

use Illuminate\Database\Eloquent\Model;

class CbamCertificate extends Model
{
    protected $table = 'tf_cbam_certificates';

    protected $fillable = [
        'cert_code',
        'container_number',
        'origin_factory_code',
        'commodity_code',
        'net_mass_tons',
        'embedded_emissions_tco2',
        'cbam_benchmark_factor',
        'cbam_levy_cost_idr',
        'certificate_hash',
        'status',
    ];

    protected $casts = [
        'net_mass_tons' => 'decimal:2',
        'embedded_emissions_tco2' => 'decimal:2',
    ];
}
