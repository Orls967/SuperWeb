<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferPricingBenchmark extends Model
{
    protected $table = 'ic_tp_benchmarks';

    protected $fillable = [
        'rule_id',
        'comparator_name',
        'financial_year',
        'operating_margin_bps',
        'gross_margin_bps',
        'source_dataset',
    ];

    protected $casts = [
        'operating_margin_bps' => 'integer',
        'gross_margin_bps' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TransferPricingRule::class, 'rule_id');
    }
}
