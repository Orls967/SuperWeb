<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VmiAgreement extends Model
{
    protected $table = 'prc_vmi_agreements';

    protected $fillable = [
        'agreement_code',
        'supplier_id',
        'sku',
        'reorder_point',
        'reorder_qty',
        'unit_cost_idr',
        'contract_plafond_idr',
        'status',
        'valid_until',
    ];

    protected $casts = [
        'reorder_point' => 'integer',
        'reorder_qty' => 'integer',
        'unit_cost_idr' => 'integer',
        'contract_plafond_idr' => 'integer',
        'valid_until' => 'datetime',
    ];
}
