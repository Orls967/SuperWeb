<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EfSubholdingDividend extends Model
{
    protected $table = 'ef_subholding_dividends';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'verified_net_profit_minor' => 'integer',
        'dividend_payout_ratio_pct' => 'float',
        'dividend_declared_minor' => 'integer',
    ];
}
