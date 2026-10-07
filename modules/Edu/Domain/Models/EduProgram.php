<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduProgram extends Model
{
    protected $table = 'edu_programs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'total_sessions' => 'integer',
        'tuition_fee_minor' => 'integer',
    ];
}
