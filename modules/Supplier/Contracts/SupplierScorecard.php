<?php

declare(strict_types=1);

namespace Modules\Supplier\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for SupplierScorecard model.
 */
class SupplierScorecard extends Model
{
    protected $table = 'sup_scorecards';

    protected $fillable = [
        'supplier_id',
        'period',
        'otd_score',
        'quality_score',
        'price_score',
        'responsiveness_score',
        'overall_score',
        'action',
        'notes',
    ];
}
