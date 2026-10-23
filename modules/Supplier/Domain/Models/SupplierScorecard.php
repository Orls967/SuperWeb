<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplier\Contracts\SupplierScorecard as BaseSupplierScorecard;

class SupplierScorecard extends BaseSupplierScorecard
{
    protected $table = 'sup_scorecards';

    protected $fillable = [
        'supplier_id', 'period', 'otd_score', 'quality_score', 'price_score',
        'responsiveness_score', 'overall_score', 'action', 'notes',
    ];

    protected $casts = [
        'otd_score' => 'integer', 'quality_score' => 'integer', 'price_score' => 'integer',
        'responsiveness_score' => 'integer', 'overall_score' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
