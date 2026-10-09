<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialSite extends Model
{
    protected $table = 'hsp_trial_sites';

    protected $fillable = [
        'trial_id',
        'site_code',
        'hospital_name',
        'principal_investigator',
    ];

    public function trial(): BelongsTo
    {
        return $this->belongsTo(ClinicalTrial::class, 'trial_id');
    }
}
