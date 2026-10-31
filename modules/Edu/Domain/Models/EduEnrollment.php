<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduEnrollment extends Model
{
    protected $table = 'edu_enrollments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'enrollment_code',
        'student_user_id',
        'student_party_id',
        'cohort_id',
        'corporate_quota_id',
        'corporate_client_id',
        'amount_paid_minor',
        'sessions_attended',
        'total_sessions',
        'refund_amount_minor',
        'status',
        'idempotency_key',
        'prerequisite_waiver_reason',
    ];

    protected $casts = [
        'amount_paid_minor' => 'integer',
        'sessions_attended' => 'integer',
        'total_sessions' => 'integer',
        'refund_amount_minor' => 'integer',
        'student_user_id' => 'integer',
    ];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(EduCohort::class, 'cohort_id', 'id');
    }

    public function corporateQuota(): BelongsTo
    {
        return $this->belongsTo(EduCorporateQuota::class, 'corporate_quota_id', 'id');
    }

    public function studentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id', 'id');
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(EduExamAttempt::class, 'enrollment_id', 'id');
    }
}
