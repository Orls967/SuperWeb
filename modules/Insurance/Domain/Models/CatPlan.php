<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CatPlan extends Model
{
    protected $table = 'ins_cat_plans';

    /** @var list<string> */
    protected $fillable = [
        'run_id',
        'structure_code',
        'attachment_idr',
        'limit_idr',
        'reinstatements',
        'layer_premium_idr',
        'ceded_aal_idr',
        'net_pml_rp100_idr',
        'net_pml_rp250_idr',
        'net_cost_idr',
    ];
}
