<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduJobOpening extends Model
{
    protected $table = 'edu_job_openings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'required_skills' => 'array',
        'salary_budget_minor' => 'integer',
    ];
}
