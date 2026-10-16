<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduJobOpening extends Model
{
    protected $table = 'edu_job_openings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'job_code',
        'title',
        'hiring_entity_id',
        'location_city',
        'required_skills',
        'salary_budget_minor',
        'job_type',
        'status',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'salary_budget_minor' => 'integer',
    ];
}
