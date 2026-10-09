<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduTalentProfile extends Model
{
    protected $table = 'edu_talent_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'skills' => 'array',
        'salary_expectation_minor' => 'integer',
        'years_experience' => 'integer',
    ];
}
