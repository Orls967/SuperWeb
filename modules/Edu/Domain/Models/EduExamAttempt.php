<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduExamAttempt extends Model
{
    protected $table = 'edu_exam_attempts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'attempt_code',
        'student_party_uuid',
        'enrollment_code',
        'exam_title',
        'score',
        'passing_score',
        'is_passed',
        'proctor_verification_hash',
        'status',
    ];

    protected $casts = [
        'score' => 'integer',
        'passing_score' => 'integer',
        'is_passed' => 'boolean',
    ];
}
