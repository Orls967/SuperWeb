<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EpidemicSurveillance extends Model
{
    protected $table = 'hsp_epidemic_surveillances';

    protected $fillable = [
        'cluster_code',
        'region_code',
        'disease_syndrome',
        'case_count',
        'alert_threshold',
        'outbreak_alarm_triggered',
        'status',
    ];

    protected $casts = [
        'outbreak_alarm_triggered' => 'boolean',
    ];
}
