<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class DcPowerReading extends Model
{
    use HasUuids;

    protected $table = 'tlx_dc_power_readings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'dc_id',
        'reading_at',
        'total_facility_energy_kwh',
        'it_equipment_energy_kwh',
        'peak_power_kw',
    ];

    protected $casts = [
        'total_facility_energy_kwh' => 'integer',
        'it_equipment_energy_kwh' => 'integer',
        'peak_power_kw' => 'integer',
        'reading_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('DcPowerReading is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('DcPowerReading is append-only and cannot be deleted.');
        });
    }

    public function dataCenter(): BelongsTo
    {
        return $this->belongsTo(TelecomDataCenter::class, 'dc_id');
    }
}
