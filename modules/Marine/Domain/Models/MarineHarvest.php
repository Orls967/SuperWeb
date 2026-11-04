<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarineHarvest extends Model
{
    protected $table = 'mar_harvests';

    protected $fillable = [
        'harvest_number',
        'cycle_id',
        'harvest_date',
        'harvested_count',
        'avg_weight_grams',
        'total_weight_kg',
        'grade_a_kg',
        'grade_a_price',
        'grade_b_kg',
        'grade_b_price',
        'grade_c_kg',
        'grade_c_price',
        'gross_amount',
        'fcr_scaled',
    ];

    protected $casts = [
        'cycle_id' => 'integer',
        'harvest_date' => 'date',
        'harvested_count' => 'integer',
        'avg_weight_grams' => 'integer',
        'total_weight_kg' => 'integer',
        'grade_a_kg' => 'integer',
        'grade_a_price' => 'integer',
        'grade_b_kg' => 'integer',
        'grade_b_price' => 'integer',
        'grade_c_kg' => 'integer',
        'grade_c_price' => 'integer',
        'gross_amount' => 'integer',
        'fcr_scaled' => 'integer',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(MarineCycle::class, 'cycle_id');
    }
}
