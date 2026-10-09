<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalTrial extends Model
{
    protected $table = 'hsp_trials';

    protected $fillable = [
        'trial_code',
        'title',
        'phase',
        'sponsor_name',
        'target_subjects',
        'status',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(TrialSite::class, 'trial_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(TrialSubject::class, 'trial_id');
    }
}
