<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EduCertificate extends Model
{
    protected $table = 'edu_certificates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'certificate_number',
        'enrollment_id',
        'student_party_id',
        'program_id',
        'skill_competency_code',
        'certificate_hash',
        'qr_verification_url',
        'issued_at',
        'expires_at',
        'cpd_points_earned',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'cpd_points_earned' => 'integer',
    ];
}
