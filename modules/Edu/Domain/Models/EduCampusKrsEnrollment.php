<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EduCampusKrsEnrollment extends Model
{
    protected $table = 'edu_campus_krs_enrollments';

    protected $fillable = [
        'class_id',
        'student_user_id',
        'status',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(EduCampusClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
