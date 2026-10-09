<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EfMaAcquisition extends Model
{
    protected $table = 'ef_ma_acquisitions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'valuation_minor' => 'integer',
        'deal_value_minor' => 'integer',
        'backfilled_entity_ids' => 'array',
    ];
}
