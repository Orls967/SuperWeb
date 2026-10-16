<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

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
        'status',
    ];

    protected $casts = [
        'total_sessions' => 'integer',
        'tuition_fee_minor' => 'integer',
    ];
}
