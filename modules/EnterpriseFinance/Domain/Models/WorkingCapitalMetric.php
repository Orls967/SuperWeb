<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingCapitalMetric extends Model
{
    protected $table = 'ef_working_capital_metrics';

    protected $fillable = [
        'entity_code',
        'period',
        'revenue_minor',
        'cogs_minor',
        'ar_balance_minor',
        'inventory_balance_minor',
        'ap_balance_minor',
        'dso_days',
        'dio_days',
        'dpo_days',
        'ccc_days',
        'efficiency_tier',
    ];

    protected $casts = [
        'revenue_minor' => 'integer',
        'cogs_minor' => 'integer',
        'ar_balance_minor' => 'integer',
        'inventory_balance_minor' => 'integer',
        'ap_balance_minor' => 'integer',
        'dso_days' => 'integer',
        'dio_days' => 'integer',
        'dpo_days' => 'integer',
        'ccc_days' => 'integer',
    ];

    public function netWorkingCapitalMinor(): int
    {
        return ($this->ar_balance_minor + $this->inventory_balance_minor) - $this->ap_balance_minor;
    }
}
