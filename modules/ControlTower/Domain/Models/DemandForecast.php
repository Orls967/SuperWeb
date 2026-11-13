<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandForecast extends Model
{
    protected $table = 'sct_demand_forecasts';

    protected $fillable = [
        'forecast_code',
        'item_code',
        'period',
        'algorithm_model',
        'forecast_qty',
        'actual_qty',
        'mape_percent',
        'mae_scaled',
        'candidates_json',
        'certificate_hash',
        'history_json',
        'is_overridden',
        'override_reason',
    ];

    protected $casts = [
        'forecast_qty' => 'integer',
        'actual_qty' => 'integer',
        'mape_percent' => 'float',
        'mae_scaled' => 'integer',
        'candidates_json' => 'array',
        'history_json' => 'array',
        'is_overridden' => 'boolean',
    ];

    /**
     * @return HasMany<ForecastOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(ForecastOverride::class, 'demand_forecast_id');
    }
}
