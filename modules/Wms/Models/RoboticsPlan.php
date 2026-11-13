<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rencana automasi & ROI robotika per gudang (Fase 279).
 */
class RoboticsPlan extends Model
{
    protected $table = 'wms_robotics_plans';

    protected $guarded = ['id'];

    protected $casts = [
        'target_lines_per_hour' => 'integer',
        'capex_idr' => 'integer',
        'annual_savings_idr' => 'integer',
        'roi_bps' => 'integer',
        'payback_months' => 'integer',
    ];
}
