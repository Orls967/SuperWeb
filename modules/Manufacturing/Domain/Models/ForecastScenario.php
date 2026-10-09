<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Skenario forecast/demand ber-versi (draft → active → archived). */
class ForecastScenario extends Model
{
    use HasUuids;

    protected $table = 'mfg_forecast_scenarios';

    protected $fillable = ['name', 'version', 'status', 'notes', 'created_by_user_id'];

    protected $casts = ['version' => 'integer', 'created_by_user_id' => 'integer'];

    public function lines(): HasMany
    {
        return $this->hasMany(ForecastLine::class, 'scenario_id');
    }
}
