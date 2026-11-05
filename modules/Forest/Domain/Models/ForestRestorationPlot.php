<?php

declare(strict_types=1);

namespace Modules\Forest\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ForestRestorationPlot extends Model
{
    protected $table = 'for_restoration_plots';

    protected $fillable = [
        'restoration_code',
        'plot_id',
        'planted_hectares',
        'trees_planted',
        'survival_rate_pct',
        'satellite_ndvi_score',
        'carbon_credits_issued',
    ];

    protected $casts = [
        'plot_id' => 'integer',
        'planted_hectares' => 'integer',
        'trees_planted' => 'integer',
        'survival_rate_pct' => 'integer',
        'satellite_ndvi_score' => 'integer',
        'carbon_credits_issued' => 'integer',
    ];

    public function plot(): BelongsTo
    {
        return $this->belongsTo(ForestConcessionPlot::class, 'plot_id');
    }
}
