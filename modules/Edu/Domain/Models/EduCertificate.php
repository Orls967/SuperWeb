<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduCertificate extends Model
{
    protected $table = 'edu_certificates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'certificate_number',
        'enrollment_id',
        'student_user_id',
        'student_party_id',
        'program_id',
        'exam_attempt_id',
        'skill_competency_code',
        'certificate_hash',
        'prev_hash',
        'qr_token',
        'qr_verification_url',
        'issued_at',
        'expires_at',
        'cpd_points_earned',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'cpd_points_earned' => 'integer',
        'student_user_id' => 'integer',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EduCertificate $cert) {
            if (empty($cert->enrollment_id)) {
                $cert->enrollment_id = 'ENR-DEFAULT';
            }
            if (empty($cert->student_party_id)) {
                $cert->student_party_id = 'student-'.($cert->student_user_id ?? 'direct');
            }
            if (empty($cert->skill_competency_code)) {
                $cert->skill_competency_code = 'COMP-DEFAULT';
            }
            if (empty($cert->qr_verification_url)) {
                $cert->qr_verification_url = url('/academy/certificates/verify?token='.($cert->qr_token ?? 'none'));
            }
            if (empty($cert->prev_hash)) {
                $cert->prev_hash = str_repeat('0', 64);
            }
        });

        static::updating(function (EduCertificate $cert) {
            if ($cert->isDirty(['certificate_number', 'certificate_hash', 'prev_hash', 'student_user_id', 'program_id'])) {
                throw new DomainException('Certificate integrity facts cannot be modified once issued (PR5).');
            }
        });

        static::deleting(function () {
            throw new DomainException('EduCertificate is an immutable record; deletions are prohibited (PR5).');
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(EduProgram::class, 'program_id', 'id');
    }

    public function studentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id', 'id');
    }

    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(EduExamAttempt::class, 'exam_attempt_id', 'id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(EduEnrollment::class, 'enrollment_id', 'id');
    }
}
