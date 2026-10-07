<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\Vehicle;

class TelematicsDevice extends Model
{
    protected $table = 'oto_telematics_devices';

    protected $fillable = [
        'device_id',
        'vehicle_id',
        'serial_number',
        'protocol',
        'status',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
