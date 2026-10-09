<?php

namespace Modules\Agri\Domain\Models\Ndvi;

use Illuminate\Database\Eloquent\Model;

class AgriFinancingInstallment extends Model
{
    protected $table = 'agri_financing_installments';

    protected $guarded = [];

    protected $casts = [
        'required_min_ndvi' => 'decimal:3',
        'evaluated_ndvi' => 'decimal:3',
        'disbursed_at' => 'datetime',
    ];
}
