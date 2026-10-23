<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EnterpriseTaxSummary extends Model
{
    protected $table = 'ef_tax_summaries';

    protected $fillable = [
        'period',
        'tax_type',
        'tax_base_idr',
        'input_tax_idr',
        'output_tax_idr',
        'withheld_tax_idr',
        'payable_or_refundable_idr',
        'status',
    ];

    protected $casts = [
        'tax_base_idr' => 'integer',
        'input_tax_idr' => 'integer',
        'output_tax_idr' => 'integer',
        'withheld_tax_idr' => 'integer',
        'payable_or_refundable_idr' => 'integer',
    ];
}
