<?php

declare(strict_types=1);

namespace Modules\Agri\Domain\Models\Ndvi;

use Illuminate\Database\Eloquent\Model;

class AgriFinancingInstallment extends Model
{
    protected $table = 'agri_financing_installments';

    protected $fillable = [
        'installment_code',
        'contract_farming_code',
        'plot_code',
        'tranche_number',
        'amount_idr',
        'required_min_ndvi',
        'evaluated_ndvi',
        'status',
        'corrective_action_plan',
        'disbursed_at',
    ];

    protected $casts = [
        'required_min_ndvi' => 'decimal:3',
        'evaluated_ndvi' => 'decimal:3',
        'disbursed_at' => 'datetime',
    ];
}
