<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastOverride extends Model
{
    protected $table = 'sct_forecast_overrides';

    protected $fillable = [
        'demand_forecast_id',
        'previous_qty',
        'new_qty',
        'reason',
        'overridden_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'previous_qty' => 'integer',
            'new_qty' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DemandForecast, $this>
     */
    public function forecast(): BelongsTo
    {
        return $this->belongsTo(DemandForecast::class, 'demand_forecast_id');
    }
}
