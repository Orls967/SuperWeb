<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduCohort extends Model
{
    protected $table = 'edu_cohorts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cohort_code',
        'program_id',
        'instructor_party_id',
        'start_date',
        'end_date',
        'max_capacity',
        'enrolled_count',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'max_capacity' => 'integer',
        'enrolled_count' => 'integer',
    ];
}
