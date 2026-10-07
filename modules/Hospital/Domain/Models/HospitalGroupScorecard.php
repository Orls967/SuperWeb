<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HospitalGroupScorecard extends Model
{
    protected $table = 'hsp_group_scorecards';

    protected $fillable = [
        'scorecard_code',
        'hospital_code',
        'hospital_name',
        'period_year',
        'period_month',
        'total_admissions',
        'mortality_rate_percent',
        'readmission_rate_percent',
        'patient_satisfaction_score',
        'total_revenue_idr',
        'bpjs_receivable_idr',
        'insurance_receivable_idr',
        'overall_quality_score',
    ];
}
