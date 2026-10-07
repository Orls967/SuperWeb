<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BloodBag extends Model
{
    protected $table = 'hsp_blood_bags';

    protected $fillable = [
        'bag_serial_number',
        'blood_type',
        'component_type',
        'volume_ml',
        'expiry_date',
        'donor_screening_hash',
        'reserved_for_encounter_id',
        'issued_to_encounter_id',
        'status',
    ];
}
