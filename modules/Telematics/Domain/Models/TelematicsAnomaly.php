<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TelematicsAnomaly extends Model
{
    protected $table = 'oto_telematics_anomalies';

    protected $fillable = [
        'vehicle_id',
        'metric',
        'anomaly_date',
        'observed_value',
        'threshold_value',
        'draft_booking_id',
    ];

    protected $casts = [
        'anomaly_date' => 'string',
        'observed_value' => 'integer',
        'threshold_value' => 'integer',
        'draft_booking_id' => 'integer',
    ];
}
