<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HospitalBed extends Model
{
    protected $table = 'hsp_beds';

    protected $fillable = [
        'bed_code',
        'ward_name',
        'room_number',
        'bed_class',
        'rate_per_day_idr',
        'status',
        'current_encounter_id',
    ];
}
