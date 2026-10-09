<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TaxTreaty extends Model
{
    protected $table = 'intl_tax_treaties';

    protected $guarded = [];

    protected $casts = [
        'standard_wht_rate' => 'float',
        'treaty_royalty_rate' => 'float',
        'treaty_interest_rate' => 'float',
        'treaty_dividend_rate' => 'float',
        'treaty_services_rate' => 'float',
        'dgt_form_required' => 'boolean',
    ];
}
