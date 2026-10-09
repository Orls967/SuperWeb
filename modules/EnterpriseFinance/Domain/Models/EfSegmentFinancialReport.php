<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EfSegmentFinancialReport extends Model
{
    protected $table = 'ef_segment_financial_reports';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'segment_contributions' => 'array',
        'total_consolidated_revenue_minor' => 'integer',
        'total_consolidated_ebitda_minor' => 'integer',
    ];
}
