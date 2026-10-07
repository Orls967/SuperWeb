<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduHeadhunterContract extends Model
{
    protected $table = 'edu_headhunter_contracts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'candidate_first_month_salary_minor' => 'integer',
        'fee_percentage' => 'float',
        'fee_amount_minor' => 'integer',
        'warranty_days' => 'integer',
        'hired_date' => 'date',
        'warranty_ends_date' => 'date',
    ];
}
