<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentRoi extends Model
{
    protected $table = 'hsp_equipment_rois';

    protected $fillable = [
        'equipment_code',
        'modality',
        'capital_expenditure_idr',
        'cumulative_revenue_idr',
        'total_procedures_done',
        'roi_percentage',
    ];
}
