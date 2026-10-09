<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HealthCommandCenterForecast extends Model
{
    protected $table = 'hsp_command_center_forecasts';

    protected $fillable = [
        'forecast_code',
        'target_date',
        'predicted_admissions',
        'recommended_nurse_shifts',
        'recommended_active_beds',
        'blood_units_needed',
    ];
}
