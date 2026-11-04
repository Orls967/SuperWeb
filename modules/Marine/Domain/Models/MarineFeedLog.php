<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarineFeedLog extends Model
{
    protected $table = 'mar_feed_logs';

    protected $fillable = [
        'cycle_id',
        'feed_date',
        'quantity_kg',
        'cost_per_kg',
        'total_cost',
        'loan_disbursed',
    ];

    protected $casts = [
        'cycle_id' => 'integer',
        'quantity_kg' => 'integer',
        'cost_per_kg' => 'integer',
        'total_cost' => 'integer',
        'loan_disbursed' => 'boolean',
        'feed_date' => 'date',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MarineCycle::class, 'cycle_id');
    }
}
