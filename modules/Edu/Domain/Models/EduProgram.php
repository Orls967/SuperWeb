<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduProgram extends Model
{
    protected $table = 'edu_programs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'program_code',
        'title',
        'industry_sector',
        'prerequisite_program_id',
        'total_sessions',
        'tuition_fee_minor',
        'passing_score',
        'validity_months',
        'status',
    ];

    protected $casts = [
        'total_sessions' => 'integer',
        'tuition_fee_minor' => 'integer',
        'passing_score' => 'integer',
        'validity_months' => 'integer',
    ];

    public function prerequisites(): HasMany
    {
        return $this->hasMany(EduPrerequisite::class, 'program_id', 'id');
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(EduCohort::class, 'program_id', 'id');
    }
}
