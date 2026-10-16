<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ReclamationProvision extends Model
{
    protected $table = 'min_reclamation_provisions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'site_id',
        'plan_code',
        'target_hectares',
        'provision_accrued_idr',
        'provision_released_idr',
        'verified_growth_ndvi',
        'status',
        'accrual_ledger_tx_id',
        'release_ledger_tx_id',
    ];

    protected $casts = [
        'target_hectares' => 'float',
        'provision_accrued_idr' => 'integer',
        'provision_released_idr' => 'integer',
        'verified_growth_ndvi' => 'float',
    ];
}
