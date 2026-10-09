<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalEquipment extends Model
{
    protected $table = 'hsp_medical_equipments';

    protected $fillable = [
        'equipment_code',
        'name',
        'category',
        'calibration_expires_at',
        'calibration_certificate_hash',
        'status',
    ];
}
