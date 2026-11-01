<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduExamAttempt extends Model
{
    protected $table = 'edu_exam_attempts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'attempt_code',
        'student_user_id',
        'student_party_uuid',
        'enrollment_id',
        'enrollment_code',
        'exam_title',
        'score',
        'passing_score',
        'is_passed',
        'proctor_verification_hash',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'score' => 'integer',
        'passing_score' => 'integer',
        'is_passed' => 'boolean',
        'student_user_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (EduExamAttempt $attempt) {
            if (empty($attempt->exam_title)) {
                $attempt->exam_title = 'Standard Competency Examination';
            }
            if (empty($attempt->student_party_uuid)) {
                $attempt->student_party_uuid = 'student-'.($attempt->student_user_id ?? 'direct');
            }
            if (empty($attempt->enrollment_code)) {
                $attempt->enrollment_code = 'ENR-'.($attempt->enrollment_id ?? 'direct');
            }
        });

        static::updating(function () {
            throw new DomainException('EduExamAttempt is an immutable append-only record; updates are rejected (PR5).');
        });

        static::deleting(function () {
            throw new DomainException('EduExamAttempt is an immutable append-only record; deletions are rejected (PR5).');
        });
    }

    public function studentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id', 'id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(EduEnrollment::class, 'enrollment_id', 'id');
    }
}
