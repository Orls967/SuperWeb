<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EfMaAcquisition extends Model
{
    protected $table = 'ef_ma_acquisitions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'deal_code',
        'target_company_name',
        'target_industry',
        'valuation_minor',
        'deal_value_minor',
        'integration_playbook_status',
        'backfilled_entity_ids',
    ];

    protected $casts = [
        'valuation_minor' => 'integer',
        'deal_value_minor' => 'integer',
        'backfilled_entity_ids' => 'array',
    ];
}
