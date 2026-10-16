<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SubsidiaryNci extends Model
{
    protected $table = 'ic_subsidiary_nci';

    protected $fillable = [
        'subsidiary_name',
        'parent_ownership_percent',
        'nci_ownership_percent',
        'net_income_idr',
        'nci_share_net_income_idr',
    ];

    protected $casts = [
        'parent_ownership_percent' => 'float',
        'nci_ownership_percent' => 'float',
        'net_income_idr' => 'integer',
        'nci_share_net_income_idr' => 'integer',
    ];
}
