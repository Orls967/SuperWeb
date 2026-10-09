<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris forecast/demand per material per periode. */
class ForecastLine extends Model
{
    protected $table = 'mfg_forecast_lines';

    protected $fillable = ['scenario_id', 'material_id', 'period_start', 'qty', 'kind'];

    protected $casts = ['period_start' => 'date', 'qty' => 'decimal:6'];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ForecastScenario::class, 'scenario_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
