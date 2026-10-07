<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduEnrollment extends Model
{
    protected $table = 'edu_enrollments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'amount_paid_minor' => 'integer',
        'sessions_attended' => 'integer',
        'total_sessions' => 'integer',
        'refund_amount_minor' => 'integer',
    ];
}
