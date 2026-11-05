<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EduCampusTranscript extends Model
{
    protected $table = 'edu_campus_transcripts';

    protected $fillable = [
        'student_user_id',
        'course_id',
        'term_id',
        'grade_point',
        'letter_grade',
        'prev_hash',
        'certificate_hash',
        'is_locked',
    ];

    protected $casts = [
        'grade_point' => 'float',
        'is_locked' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(EduCampusCourse::class, 'course_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(EduCampusTerm::class, 'term_id');
    }
}
